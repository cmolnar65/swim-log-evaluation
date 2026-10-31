<?php
namespace SwimLogEvaluation;
if ( ! defined('ABSPATH') ) exit;
require_once __DIR__ . '/class-database.php';

/** Canonical read-only public projection and current-owner visibility commands. */
final class Workout_Visibility {
    const TYPES = array('pool','open_water','running','walking');
    const ROUTES_META = 'swimlog_public_routes';

    /** Interactive management authority is never inferred from read/public access. */
    public static function owner_error( $owner ) {
        if ( ! is_user_logged_in() || (int)$owner !== (int)get_current_user_id() || ! current_user_can('swimlog_manage_own_workouts') ) {
            return new \WP_Error('swimlog_owner_required',__('Only the authenticated workout owner may manage this record.','chriss-swim-training-progress-evaluation'),array('status'=>403));
        }
        return null;
    }
    private static function unavailable() {
        return new \WP_Error('swimlog_public_not_found',__('Workout not found.','chriss-swim-training-progress-evaluation'),array('status'=>404));
    }
    public static function ready() {
        global $wpdb;
        $table=Database::table('workout_visibility');
        if ( $table !== Database::read('get_var',$wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table))) ) return false;
        return 'ready' === Database::read('get_var',$wpdb->prepare('SELECT visibility FROM %i WHERE workout_id=0',$table));
    }
    /** One transaction snapshots the old explicitly public swims and a durable completion marker. */
    public static function migrate() {
        global $wpdb;
        $table=Database::table('workout_visibility');
        if ( false === Database::write('query','START TRANSACTION') ) return false;
        try {
            if ( false === Database::write('query',$wpdb->prepare("INSERT IGNORE INTO %i (workout_id,owner_id,visibility,route_public,revision,updated_at_gmt) VALUES (0,0,'pending',0,1,%s)",$table,gmdate('Y-m-d H:i:s'))) ) throw new \RuntimeException('marker');
            $state=Database::read('get_var',$wpdb->prepare('SELECT visibility FROM %i WHERE workout_id=0 FOR UPDATE',$table));
            if ( 'ready' !== $state ) {
                // Existing rows/explicit decisions win on retries. No site-default or provisional preference participates.
                if ( false === Database::write('query',$wpdb->prepare("INSERT IGNORE INTO %i (workout_id,owner_id,visibility,route_public,revision,updated_at_gmt)
                    SELECT w.id,w.user_id,'legacy_public',CASE WHEN w.workout_type='open_water' AND EXISTS (SELECT 1 FROM %i r WHERE r.user_id=w.user_id AND r.meta_key='swimlog_public_ows_routes' AND r.meta_value='1') THEN 1 ELSE 0 END,1,%s
                    FROM %i w WHERE w.workout_type IN ('pool','open_water') AND EXISTS (SELECT 1 FROM %i u WHERE u.user_id=w.user_id AND u.meta_key='swimlog_public_results' AND u.meta_value='1') AND NOT EXISTS (SELECT 1 FROM %i n WHERE n.user_id=w.user_id AND n.meta_key='swimlog_public_results' AND n.meta_value<>'1')",
                    $table,$wpdb->usermeta,gmdate('Y-m-d H:i:s'),Database::table('workouts'),$wpdb->usermeta,$wpdb->usermeta)) ) throw new \RuntimeException('snapshot');
                if ( false === Database::write('update',$table,array('visibility'=>'ready'),array('workout_id'=>0)) ) throw new \RuntimeException('completion');
            }
            if ( false === Database::write('query','COMMIT') ) throw new \RuntimeException('commit');
            return true;
        } catch ( \Throwable $error ) { Database::write('query','ROLLBACK'); return false; }
    }
    private static function owned_workout( $id ) {
        global $wpdb;
        $error=self::owner_error(get_current_user_id()); if($error)return$error;
        $row=Database::read('get_row',$wpdb->prepare('SELECT * FROM %i WHERE id=%d AND user_id=%d',Database::table('workouts'),absint($id),get_current_user_id()));
        return $row && in_array($row->workout_type,self::TYPES,true) ? $row : self::unavailable();
    }
    private static function state_row( $id, $owner ) {
        global $wpdb;
        return Database::read('get_row',$wpdb->prepare('SELECT * FROM %i WHERE workout_id=%d AND owner_id=%d',Database::table('workout_visibility'),$id,$owner));
    }
    private static function global_public( $owner ) { return '1' === (string)get_user_meta($owner,'swimlog_public_results',true); }
    private static function route_allowed( $workout, $state ) {
        if ( ! $state || ! (int)$state->route_public ) return false;
        if ( '1' === (string)get_user_meta($workout->user_id,self::ROUTES_META,true) ) return true;
        return in_array($workout->workout_type,array('pool','open_water'),true) && '1' === (string)get_user_meta($workout->user_id,'swimlog_public_ows_routes',true);
    }
    public static function get_for_owner( $workout_id ) {
        $w=self::owned_workout($workout_id);if(is_wp_error($w))return$w;
        if(!self::ready())return new \WP_Error('swimlog_visibility_upgrade',__('Visibility upgrade is not complete. Sharing is unavailable.','chriss-swim-training-progress-evaluation'),array('status'=>503));
        $s=self::state_row($w->id,$w->user_id);$public=$s && in_array($s->visibility,array('public','legacy_public'),true);
        $global=self::global_public($w->user_id);
        return array('workout_id'=>(int)$w->id,'visibility'=>$s?$s->visibility:'private','route_public'=>$s?(bool)$s->route_public:false,'effective_public'=>(bool)($public&&$global),'effective_route_public'=>(bool)($public&&$global&&self::route_allowed($w,$s)),'blocked_reason'=>!$global?'public_results_disabled':(!$public?'workout_private':null),'pending_standalone_preference'=>'1'===(string)get_user_meta($w->user_id,'_standalone_workout_public_'.$w->id,true),'revision'=>$s?(int)$s->revision:0);
    }
    public static function set_for_owner( $workout_id, array $changes, $nonce, $expected_revision ) {
        global $wpdb;
        $w=self::owned_workout($workout_id);if(is_wp_error($w))return$w;
        if(!is_string($nonce)||!wp_verify_nonce($nonce,'swimlog_workout_visibility_'.$w->id))return new \WP_Error('swimlog_visibility_nonce',__('Reload the workout before changing visibility.','chriss-swim-training-progress-evaluation'),array('status'=>403));
        if(!self::ready())return new \WP_Error('swimlog_visibility_upgrade',__('Visibility upgrade is not complete.','chriss-swim-training-progress-evaluation'),array('status'=>503));
        if(array_diff(array_keys($changes),array('visibility','route_public'))||!in_array($changes['visibility']??null,array('private','public'),true)||!is_bool($changes['route_public']??null)||!is_int($expected_revision)||$expected_revision<0||('private'===$changes['visibility']&&$changes['route_public']))return new \WP_Error('swimlog_visibility_input',__('Choose explicit workout and route visibility. Private workouts cannot share routes.','chriss-swim-training-progress-evaluation'),array('status'=>400));
        $t=Database::table('workout_visibility');$next=$expected_revision+1;
        if(0===$expected_revision){$ok=Database::write('query',$wpdb->prepare('INSERT IGNORE INTO %i (workout_id,owner_id,visibility,route_public,revision,updated_at_gmt) SELECT id,user_id,%s,%d,%d,%s FROM %i WHERE id=%d AND user_id=%d',$t,$changes['visibility'],(int)$changes['route_public'],$next,gmdate('Y-m-d H:i:s'),Database::table('workouts'),$w->id,$w->user_id));}
        else{$ok=Database::write('query',$wpdb->prepare('UPDATE %i v INNER JOIN %i w ON w.id=v.workout_id AND w.user_id=v.owner_id SET v.visibility=%s,v.route_public=%d,v.revision=%d,v.updated_at_gmt=%s WHERE v.workout_id=%d AND v.owner_id=%d AND v.revision=%d',$t,Database::table('workouts'),$changes['visibility'],(int)$changes['route_public'],$next,gmdate('Y-m-d H:i:s'),$w->id,$w->user_id,$expected_revision));}
        if(1!==$ok)return new \WP_Error('swimlog_visibility_conflict',__('Visibility changed or could not be saved. Reload before trying again.','chriss-swim-training-progress-evaluation'),array('status'=>409));
        delete_user_meta($w->user_id,'_standalone_workout_public_'.$w->id);
        self::no_cache();return self::get_for_owner($w->id);
    }
    public static function adopt_standalone_preference( $workout_id, $nonce, $expected_revision ) {
        $state=self::get_for_owner($workout_id);if(is_wp_error($state))return$state;
        if(!$state['pending_standalone_preference'])return new \WP_Error('swimlog_visibility_no_preference',__('There is no pending standalone sharing request.','chriss-swim-training-progress-evaluation'),array('status'=>409));
        return self::set_for_owner($workout_id,array('visibility'=>'public','route_public'=>false),$nonce,$expected_revision);
    }
    public static function no_cache() { require_once __DIR__.'/class-land-activity.php'; Land_Activity::no_cache(); if(!headers_sent())header('Cache-Control: private, no-store'); }
    public static function is_public( $workout_id ) { return !is_wp_error(self::get_public($workout_id)); }
    public static function get_public( $workout_id ) {
        global $wpdb;
        self::no_cache();if(!self::ready())return self::unavailable();
        $w=Database::read('get_row',$wpdb->prepare("SELECT w.*,v.route_public,v.visibility FROM %i w INNER JOIN %i v ON v.workout_id=w.id AND v.owner_id=w.user_id WHERE w.id=%d AND v.visibility IN ('public','legacy_public') AND w.workout_type IN ('pool','open_water','running','walking')",Database::table('workouts'),Database::table('workout_visibility'),absint($workout_id)));
        if(!$w||!self::global_public($w->user_id))return self::unavailable();
        return self::project($w);
    }
    public static function query_public( $owner_id, array $filters=array(), $page=1, $per_page=20 ) {
        global $wpdb;
        self::no_cache();$owner=absint($owner_id);$page=max(1,(int)$page);$per_page=max(1,min(100,(int)$per_page));
        $empty=array('workouts'=>array(),'total'=>0,'pages'=>0,'page'=>$page,'per_page'=>$per_page);
        if(!self::ready()||!self::global_public($owner))return$empty;
        $type=$filters['workout_type']??'';if(!in_array($type,array_merge(array(''),self::TYPES),true))return new \WP_Error('swimlog_public_filter',__('Unsupported public activity filter.','chriss-swim-training-progress-evaluation'),array('status'=>400));
        $start=$filters['date_from']??'1000-01-01';$end=$filters['date_to']??'9999-12-31';
        foreach(array($start,$end)as$date)if(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))return new \WP_Error('swimlog_public_filter',__('Use calendar dates for public history filters.','chriss-swim-training-progress-evaluation'),array('status'=>400));
        // Identical eligibility precedes count, ordering and pagination; no private-row count leaks.
        $total=(int)Database::read('get_var',$wpdb->prepare("SELECT COUNT(*) FROM %i w INNER JOIN %i v ON v.workout_id=w.id AND v.owner_id=w.user_id WHERE w.user_id=%d AND v.visibility IN ('public','legacy_public') AND w.workout_type IN ('pool','open_water','running','walking') AND (%s='' OR w.workout_type=%s) AND w.workout_start >= %s AND w.workout_start <= %s",Database::table('workouts'),Database::table('workout_visibility'),$owner,$type,$type,$start.' 00:00:00',$end.' 23:59:59'));
        $rows=Database::read('get_results',$wpdb->prepare("SELECT w.*,v.route_public,v.visibility FROM %i w INNER JOIN %i v ON v.workout_id=w.id AND v.owner_id=w.user_id WHERE w.user_id=%d AND v.visibility IN ('public','legacy_public') AND w.workout_type IN ('pool','open_water','running','walking') AND (%s='' OR w.workout_type=%s) AND w.workout_start >= %s AND w.workout_start <= %s ORDER BY w.workout_start DESC,w.id DESC LIMIT %d OFFSET %d",Database::table('workouts'),Database::table('workout_visibility'),$owner,$type,$type,$start.' 00:00:00',$end.' 23:59:59',$per_page,($page-1)*$per_page));
        return array('workouts'=>array_map(array(__CLASS__,'project'),$rows),'total'=>$total,'pages'=>(int)ceil($total/$per_page),'page'=>$page,'per_page'=>$per_page);
    }
    private static function project( $w ) {
        global $wpdb;
        $keys=array('id','workout_type','total_distance_m','original_distance','original_distance_unit','elapsed_time_ms','moving_time_ms','original_pool_length','pool_length_unit','primary_stroke','avg_swolf','equivalent_steps');$out=array();foreach($keys as$key)$out[$key]=$w->$key??null;
        $out['id']=(int)$w->id;$out['date']=substr($w->workout_start,0,10);
        $out['lengths']=array();$out['splits']=array();$out['performances']=array();
        if('pool'===$w->workout_type){$out['lengths']=Database::read('get_results',$wpdb->prepare("SELECT sequence_no,distance_m,elapsed_time_ms,stroke,stroke_count,swolf_score FROM %i WHERE workout_id=%d AND length_type='active' ORDER BY sequence_no",Database::table('lengths'),$w->id));
            $out['performances']=Database::read('get_results',$wpdb->prepare('SELECT distance_value,course_unit,stroke,duration_ms FROM %i WHERE workout_id=%d AND user_id=%d ORDER BY distance_value,stroke',Database::table('performances'),$w->id,$w->user_id));}
        else{$out['splits']=Database::read('get_results',$wpdb->prepare('SELECT sequence_no,distance_m,elapsed_time_ms,moving_time_ms FROM %i WHERE workout_id=%d ORDER BY sequence_no',Database::table('laps'),$w->id));}
        $out['route']=null;
        if(self::route_allowed($w,$w)){
            $rows=Database::read('get_results',$wpdb->prepare('SELECT sequence_no,recorded_at,latitude,longitude FROM %i WHERE workout_id=%d ORDER BY sequence_no LIMIT 25000',Database::table('trackpoints'),$w->id));
            $segments=array();$segment=array();$previous=null;
            foreach($rows as$p){$valid=is_numeric($p->latitude)&&is_numeric($p->longitude)&&abs((float)$p->latitude)<=90&&abs((float)$p->longitude)<=180;
                $gap=$previous&&((int)$p->sequence_no!==(int)$previous->sequence_no+1||(!$p->recorded_at||!$previous->recorded_at||strtotime($p->recorded_at)-strtotime($previous->recorded_at)>120||strtotime($p->recorded_at)<strtotime($previous->recorded_at)));
                if(!$valid||$gap){if($segment)$segments[]=$segment;$segment=array();}if($valid)$segment[]=array('latitude'=>(float)$p->latitude,'longitude'=>(float)$p->longitude);$previous=$valid?$p:null;}
            if($segment)$segments[]=$segment;$out['route']=array('segments'=>$segments);
        }
        return$out;
    }
    /** Eligible swimming performance inputs only; stored private-history PB flags are deliberately excluded. */
    public static function public_performances( $owner, $course ) {
        self::no_cache();
        global $wpdb;
        if(!self::ready()||!self::global_public($owner)||!in_array($course,array('m','yd'),true))return array();
        return Database::read('get_results',$wpdb->prepare("SELECT p.workout_id,p.distance_value,p.course_unit,p.stroke,p.duration_ms,DATE(p.achieved_at) AS achieved_at FROM %i p INNER JOIN %i w ON w.id=p.workout_id AND w.user_id=p.user_id INNER JOIN %i v ON v.workout_id=w.id AND v.owner_id=w.user_id WHERE p.user_id=%d AND p.course_unit=%s AND w.workout_type='pool' AND v.visibility IN ('public','legacy_public') ORDER BY p.achieved_at,p.id",Database::table('performances'),Database::table('workouts'),Database::table('workout_visibility'),absint($owner),$course));
    }
    /** Owner-only HTML adapter; mutation remains in the canonical nonce/revision service. */
    public static function controls( $id ) {
        $s=self::get_for_owner($id);if(is_wp_error($s))return;
        ?><section class="swimlog-workout-visibility" aria-labelledby="swimlog-visibility-title"><h2 id="swimlog-visibility-title"><?php esc_html_e('Public workout visibility','chriss-swim-training-progress-evaluation'); ?></h2>
        <p><?php esc_html_e('Public viewing is read-only. Notes, photos, source files, device metadata and health information are excluded. Public Results must also be enabled in Swimmer Configuration.','chriss-swim-training-progress-evaluation'); ?></p>
        <p role="status"><?php echo esc_html($s['effective_public']?__('This workout is publicly viewable.','chriss-swim-training-progress-evaluation'):__('This workout is not publicly viewable.','chriss-swim-training-progress-evaluation')); ?></p>
        <?php if('public_results_disabled'===$s['blocked_reason']):?><p><?php esc_html_e('Your Public Results setting currently blocks public viewing, even if the workout checkbox is checked.','chriss-swim-training-progress-evaluation'); ?></p><?php endif;?>
        <form method="post"><?php wp_nonce_field('swimlog_workout_visibility_'.$id); ?><input type="hidden" name="swimlog_workout_action" value="visibility"><input type="hidden" name="visibility_revision" value="<?php echo esc_attr($s['revision']); ?>">
        <p><label><input type="checkbox" name="workout_public" value="1" <?php checked(in_array($s['visibility'],array('public','legacy_public'),true)); ?>> <?php esc_html_e('Make Workout Public','chriss-swim-training-progress-evaluation'); ?></label></p>
        <p><label><input type="checkbox" name="route_public" value="1" <?php checked($s['route_public']); ?>> <?php esc_html_e('Share route publicly','chriss-swim-training-progress-evaluation'); ?></label></p>
        <p><?php esc_html_e('Route sharing additionally requires your public-route permission. Turning it off excludes all public map geometry and GPS coordinates without deleting your private recording.','chriss-swim-training-progress-evaluation'); ?></p>
        <?php if($s['pending_standalone_preference']):?><p><?php esc_html_e('The standalone app saved a sharing request. It has not granted public access. Confirming that request shares the workout only, with route sharing off.','chriss-swim-training-progress-evaluation'); ?></p><button class="button" name="adopt_standalone" value="1"><?php esc_html_e('Confirm previous sharing request','chriss-swim-training-progress-evaluation'); ?></button><?php endif;?>
        <?php submit_button(__('Save workout visibility','chriss-swim-training-progress-evaluation'),'secondary'); ?></form></section><?php
    }
    /** WordPress user-deletion lifecycle hook; never a public management endpoint. */
    public static function deleted_user( $owner ) {
        if(self::ready()&&absint($owner))Database::write('delete',Database::table('workout_visibility'),array('owner_id'=>absint($owner)));
    }
    public static function personal_bests( $owner, $course, $age_group = null ) {
        $map=array();foreach(self::public_performances($owner,$course)as$p){
            if(null!==$age_group){require_once __DIR__.'/class-age-group.php';$g=Age_Group::for_user($owner,substr($p->achieved_at,0,10));if(!$g||$g['label']!==$age_group)continue;}
            $d=(int)$p->distance_value;foreach(array($p->stroke,'OVERALL')as$stroke)if(!isset($map[$d][$stroke])||(int)$p->duration_ms<(int)$map[$d][$stroke]->duration_ms)$map[$d][$stroke]=$p;
        }return$map;
    }
    public static function progression( $owner, $distance, $course, $stroke ) {
        $out=array();$best=null;foreach(self::public_performances($owner,$course)as$p)if((int)$p->distance_value===(int)$distance&&$p->stroke===$stroke&&(null===$best||(int)$p->duration_ms<$best)){$best=(int)$p->duration_ms;$out[]=$p;}return$out;
    }

    /** Internal import/manual-create hook: explicit private rows cannot be grandfathered by an overlapping upgrade. */
    public static function created( $id, $owner ) {
        global $wpdb;
        $t=Database::table('workout_visibility');
        if($t!==Database::read('get_var',$wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($t))))return true;
        return false!==Database::write('query',$wpdb->prepare("INSERT IGNORE INTO %i (workout_id,owner_id,visibility,route_public,revision,updated_at_gmt) VALUES (%d,%d,'private',0,1,%s) ON DUPLICATE KEY UPDATE owner_id=VALUES(owner_id),visibility='private',route_public=0,revision=revision+1,updated_at_gmt=VALUES(updated_at_gmt)",$t,$id,$owner,gmdate('Y-m-d H:i:s')));
    }

}
