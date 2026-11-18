# Face attendance identity checks

Deploy this change together with the enrollment safety change in New Level Fit Master.
There is no migration and no automatic modification of enrollment or attendance data.

## Attendance

- Ambil Foto takes three camera frames, 300 ms apart. Failed attempts discard all captures.
- The server requires three distinct, valid JPEGs, at most 6 MiB decoded in total.
  The evidence photo must be the first frame in the batch.
- Each frame requires the Python service's existing `check_quality=true` support and a
  successful quality-checked response. An older service without this capability fails closed.
- The same eligible employee must be identified in every frame. There is no majority vote
  between identities and no browser-provided employee ID can select the result.
- A strict majority of an employee's valid registered samples must support each match.
  Two registered samples require both to match; three require two; five require three.
- Any competing employee with even one sample above the match threshold causes rejection,
  including when the top candidate leads by more than the configured margin.
- The existing threshold and margin still apply. Defaults remain 0.45 and 0.08; these are
  similarity settings, not accuracy percentages, and are not newly calibrated values.
- Current employee/device eligibility and the full winning profile's sample snapshot are
  checked again before saving. A changed/deleted sample requires a fresh attempt.
- Only the first frame is retained as the existing private attendance evidence photo.
  The stored score is the lowest supported score from the three matching frames.
- Candidate profile/sample IDs and scores go to private application logs, never kiosk output.

Multiple captures and quality checks reduce accidental matching; they do not provide
liveness detection, proof of camera provenance, or a guarantee against lookalike errors.
No card, PIN, identity selection, or alternate approval bypass is introduced.

## Deployment

Pull Face Attendance, then run `php artisan optimize:clear` in `laravel`.
Pull Master and clear its cache as well. Keep `FACE_RECOGNITION_THRESHOLD` aligned in
both applications. Refresh open kiosk pages; old single-photo requests are rejected.
Ensure the web server accepts the JSON capture payload (allow at least 10 MiB).
The face service already implements quality checks; deploy that existing version if
the server is older. Three sequential checks increase inference time; test on the kiosk.

## Incident review and calibration

Use Master's read-only `php artisan face:audit-enrollments` command, optionally
`--employee=ID`. Flags indicate similarity, not proof that a sample belongs to the wrong
person. The audit compares all active profiles, including different branches; an attendance
device still compares only employees eligible at its own branch (including mobile staff).

For the reported incident, obtain the two employee IDs, device branch, and timestamp;
compare the private attendance photo, recorded identity, and enrollment ownership.
Existing enrollment stores embeddings, not enrollment camera photos. An audit of vectors
cannot by itself establish the real identity of the person originally enrolled.
Do not remove samples or alter historical attendance automatically.

Choose any threshold changes using reviewed genuine and lookalike capture sets from the
actual devices. Raising a threshold can also reject genuine employees. The safeguards
here are verified with synthetic test embeddings; real-world calibration remains separate.

## Verification

`php vendor/bin/phpunit` in `laravel`, plus:

```
node laravel/tests/Browser/camera-startup.cjs
node laravel/tests/Browser/camera-captures.cjs
```
