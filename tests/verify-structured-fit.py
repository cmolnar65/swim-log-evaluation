"""Independently decode synthetic PHP exports with Garmin's official SDK."""
import base64
import json
import re
import sys
from garmin_fit_sdk import Decoder, Stream

log = open(sys.argv[1], encoding="utf-8").read()
match = re.search(r"SWIMLOG_FIT_QA_BASE64=([A-Za-z0-9+/=]+)", log)
assert match, "Missing synthetic FIT fixture"
data = base64.b64decode(match.group(1), validate=True)
decoder = Decoder(Stream.from_byte_array(bytearray(data)))
assert decoder.is_fit(), "Invalid FIT signature"
assert decoder.check_integrity(), "FIT CRC/integrity failure"
# Integrity checking consumes the stream; decode from a fresh stream.
decoder = Decoder(Stream.from_byte_array(bytearray(data)))
messages, errors = decoder.read()
assert not errors, errors
print(json.dumps(messages, default=str, indent=2))
assert messages["file_id_mesgs"][0]["type"] == "workout"
workout = messages["workout_mesgs"][0]
assert workout["sport"] == "swimming"
assert workout["sub_sport"] == "lap_swimming"
assert workout["num_valid_steps"] == 7
steps = messages["workout_step_mesgs"]
assert len(steps) == 7
assert [s["message_index"] for s in steps] == list(range(7))
assert steps[0]["duration_type"] == "distance"
assert steps[1]["duration_type"] == "repetition_time"
assert steps[4]["duration_type"] == "repeat_until_steps_cmplt"
assert steps[6]["duration_type"] == "open"
print("Official Garmin decoder: workout type, CRC, swimming metadata, repeats and rests verified.")
