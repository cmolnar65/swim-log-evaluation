# Prompt for the standalone application conversation

Continue development of **cmolnar65/swimlog-standalone** on its development branch. This task integrates the WordPress plugin's canonical workout visibility services. First inspect actual repository heads/checks and current `app/WorkoutEditor.php`, README and authentication, public rendering and mutation routes. The earlier provisional implementation checkpoint was `37c6d273067ad08eac5cdafe853d16402545446e`; treat it as a checkpoint, not current proof. Read the current plugin `develop` implementation and `docs/public-workout-visibility.md` in **cmolnar65/swim-log-evaluation** before changing code. Verify the deployed plugin supports the service; do not assume deployment occurred.

Keep WordPress accounts/authentication and the existing database/workout data. Do not duplicate storage, implement a new login system, modify plugin code in this conversation, merge main, release, tag, deploy, build plugin packages or change SVN. Plugin v1.3 remains on release hold. Implement app changes and tests on the app development branch using focused commits. Preserve existing same-origin checks, operation nonces, strict field allowlists, owner serialization/locking, stale-revision protection and photo/source privacy.

## Canonical contract to use

After the existing trusted WordPress bootstrap, use `\SwimLogEvaluation\Workout_Visibility` (abbreviated `V` below). No direct SQL or metadata writes for canonical visibility. Remove writes to `_standalone_workout_public_<id>`; it is now a pending legacy request only. Do not clear or auto-adopt pending requests on page load.

Feature-detect all required methods and `V::ready()`. If unavailable, hide/disable mutation controls and report that the plugin upgrade is required; do not fall back to provisional writes, infer permission, or expose public data. Avoid calling plugin upgrade/lifecycle helpers from the app.

Owner state:

```php
use SwimLogEvaluation\Workout_Visibility as V;

$state = V::get_for_owner($workoutId);
if (is_wp_error($state)) {
    // Map error/status; do not render another owner's state or management form.
}
$visibilityNonce = wp_create_nonce('swimlog_workout_visibility_' . $workoutId);
// Render escaped nonce and integer $state['revision'] as separate hidden fields.
```

State keys are `workout_id`, `visibility` (`private`, `public`, `legacy_public`), `route_public` (bool), `effective_public`, `effective_route_public`, `blocked_reason` (`public_results_disabled`, `workout_private`, null), `pending_standalone_preference` (bool), `revision` (int). The method derives the real authenticated owner itself; it takes no owner parameter. Never call `wp_set_current_user` from IDs or fields supplied by the browser.

Render **Make Workout Public** checked only when canonical visibility is `public` or `legacy_public`. Render **Share route publicly** from canonical `route_public`, disabled/unset when private. An old pending preference must not make the public checkbox appear granted. Show the actual effective access and any blocking master setting. Explain that routes require separate applicable owner consent; an allowed route may have no GPS data. Provide a separate explicit **Confirm previous sharing request** action only when pending.

Use a separate authenticated POST operation for visibility, with the app's same-origin/operation nonce protections PLUS this canonical nonce and revision. Strictly parse submitted revision as a nonnegative integer; reject malformed, missing, overflow or negative values. Parse checkbox values using an allowlist; do not cast arbitrary strings to bool. Do not accidentally unpublish when unrelated metadata/photo forms omit visibility fields.

```php
// Existing authenticated owner/session/capability/origin/operation-nonce checks run first.
// $revision is a validated PHP int from the canonical form, not the editor's HMAC string.
$changes = array(
    'visibility' => $makePublic ? 'public' : 'private',
    'route_public' => $makePublic && $shareRoute, // actual PHP boolean
);
$result = V::set_for_owner($workoutId, $changes, $visibilityNonceFromPost, $revision);
if (is_wp_error($result)) {
    // Show accurate failure; 409 requires reload/review, never silently retry with a fresh revision.
} else {
    // $result is refreshed canonical owner state; show effective status, not unconditional “published”.
}
```

The plugin verifies actual login, `swimlog_manage_own_workouts`, ownership, canonical nonce and revision. Nonce action must be exactly `swimlog_workout_visibility_<id>`. No manage-all exemption exists for mutations. Canonical writes are atomic CAS operations; private+route true is rejected. Explicit successful save consumes the old provisional key. Do not call services with a forged owner session or bypass safeguards.

Explicit adoption (separate button/POST):

```php
$result = V::adopt_standalone_preference($workoutId, $visibilityNonceFromPost, $revision);
```

This requires a currently pending `'1'`, saves public with routes **off**, and consumes the provisional key. No automatic migration, batch adoption, route adoption, global opt-in or post creation. Absent provisional meta is neither consent nor a reason to turn off canonical `legacy_public`. A successful explicit private save may dismiss the pending request without publishing.

## Master and route permissions

Public access requires BOTH explicit owner `swimlog_public_results='1'` AND canonical public grant. Neither is overridden. New workouts are private; toggling global results never publishes private history. Owner settings are still maintained in WordPress Swimmer Configuration; initially link/explain them rather than writing user metadata directly from the app. Do not silently enable them.

A route requires those two gates, canonical `route_public=true`, and explicit applicable owner route consent. New `swimlog_public_routes='1'` covers supported swimming/running/walking; existing `swimlog_public_ows_routes='1'` covers swimming only. Old swimming consent cannot expose land GPS. Making private clears route preference. Re-publication must not restore route permission silently. GPS flags apply consistently to supported workout types; cycling support remains deferred until plugin support exists.

## Public rendering

Build any anonymous/public view only from these plugin calls:

```php
$public = V::get_public($workoutId); // whitelist array or indistinguishable 404 WP_Error
$list = V::query_public($ownerId, array(
    'workout_type' => '', // or pool/open_water/running/walking
    'date_from' => '2026-01-01',
    'date_to' => '2026-12-31',
), 1, 20); // workouts,total,pages,page,per_page; max per_page 100
$isPublic = V::is_public($workoutId); // optional status only; do not follow with a raw lookup
```

Use `get_public` directly for rendering to avoid a check-then-raw-lookup leak. It applies the same policy even for logged-in owners/admins. If the app calls WordPress over HTTP, the plugin exposes GET-only `/wp-json/swimlog/v1/public/workouts?user=<ownerId>` and `/wp-json/swimlog/v1/public/workouts/<id>`. No public visibility-write REST endpoint exists; the same-WordPress-bootstrap app uses the owner command service above. Keep existing authenticated API opt-ins and private calendar routes independent.

Public DTO keys: `id`, `date`, `workout_type`, `total_distance_m`, `original_distance`, `original_distance_unit`, `elapsed_time_ms`, `moving_time_ms`, `original_pool_length`, `pool_length_unit`, `primary_stroke`, `avg_swolf`, `equivalent_steps`, `lengths`, `splits`, `performances`, `route`. Some metric values are nullable/numeric database strings. `lengths` entries have sequence_no/distance_m/elapsed_time_ms/stroke/stroke_count/swolf_score; `splits` sequence_no/distance_m/elapsed_time_ms/moving_time_ms; `performances` distance_value/course_unit/stroke/duration_ms. No stored global PB flag is exposed.

`route` is null unless all permissions allow geometry; otherwise `array('segments'=>array(array(array('latitude'=>float,'longitude'=>float), ...), ...))`. Plot separate segments; never bridge gaps or fetch private trackpoints to supplement them. Do not leak route points through JSON, HTML data attributes, script preload, caches, screenshots or map provider URLs when route is null. Escape all output. Public geometry contains no exact timestamps or sensors.

Do not add notes, photos, location labels, source files/paths/IDs, import/device/raw metadata, HR/cadence or measurements to public output. Do not serialize the editor's raw model or owner-only REST responses. Preserve private owner detail access separately. Land workouts remain private in existing private access paths and become public only after explicit canonical approval. Cycling stays deferred. Swimming, running and walking totals remain separate. Public aggregates must use eligible canonical inputs before count/pagination; PB/progression use `V::personal_bests($ownerId, $course, $ageGroup = null)` and `V::progression($ownerId, $distance, $course, $stroke)` if needed, not private stored PB flags.

Set public response cache headers `Cache-Control: private, no-store`; do not cache DTOs/routes or render owner-only information into cacheable public pages. Re-evaluate policy on each request. Purge any app-maintained public caches on revocation and ensure upstream caches exclude these pages. Previously copied static WordPress posts/media URLs are outside dynamic revocation; do not promise their removal.

## Editor integration and permissions

Extend the editor stale-revision HMAC/snapshot with canonical `revision` and relevant state, replacing the provisional-meta component. Keep the canonical revision separate from the editor revision, and pass the exact originally rendered revision on POST. If another tab/app changes visibility, show a conflict and reload; do not auto-merge or retry. Separate visibility submission from metadata/photo saves so failure cannot partially change sharing while showing a generic successful save. Refresh current state after changes.

Public viewing grants no write permission. Anonymous users and authenticated non-owners, including manage-all users, must be denied **server-side before side effects** for metadata, uploads/removal, rebuild, delete, conversion, plan match/unmatch/link/detach, post creation and visibility. Hide controls for usability as well. Existing plugin mutation services now reject non-owner actors; preserve all app ownership and capability checks too. HTTP mutations must verify their existing operation-specific CSRF protections, not merely rely on service ownership. Use the plugin's `Workout::delete($id, $owner)` so canonical/provisional deletion cleanup occurs; never directly delete rows.

Post creation/publication is separate. A sharing save creates no post and a post creates no sharing grant. Preserve the existing explicit create-draft flow/capabilities/nonces and dynamic workout block. A dynamic block becomes unavailable after revocation. Do not change post status from sharing controls.

Errors: owner/nonce 403, invalid changes 400, stale revision or missing pending request 409, upgrade unavailable 503, private/missing/unsupported public record 404. Use `get_error_code()`, `get_error_message()`, and status from `get_error_data()['status']` safely; never treat WP_Error as success or fall back to raw reads.

## Required verification and completion

Use synthetic fixtures only. Test anonymous/public allowed content with no writes; authenticated cross-owner/admin denied writes; actual owner valid nonce succeeds; invalid/missing nonce/origin fails; stale canonical/editor revisions; all global/workout combinations; all route gates including old OWS consent versus land; pending adoption only after explicit action; absent old key preserves canonical state; new workout private; unsupported/cycling denied; notes/photos/sensors/files/measurements excluded; pagination/totals/PBs private input exclusion; route revocation and GPS gaps; deletion cleanup; separate post creation and dynamic revocation; plugin unavailable/upgrade pending fail closed; no partial sharing from metadata/photo failures. Exercise actual handlers/HTTP as well as service calls and keep existing PHP/browser suites passing.

Inspect current app supported PHP matrix and run it fully; coordinate plugin PHP 7.4/8.0–8.5 and WordPress >=6.2 compatibility. Read actual diagnostics, fix genuine failures and commit reviewable changes. Report exact app commit, plugin compatibility tested, checks, any deployment still required and remaining physical/live acceptance limits. Do not claim sharing is deployed merely because code exists.
