# Plugin Diff Guide v1 (for `SouphianeJ/plugin_activity_moodle`)

## Goal

Align plugin behavior with SO canonical `json2activity/v1` contract:
- transport stays Moodle WS,
- response shape becomes nested (`debug`, `error`),
- activity support becomes `label|url|page|assign`,
- status WS endpoint added (`local_json2activity_get_status`),
- dry-run stays first-class.

## Files To Modify

1. `classes/processor.php`
2. `classes/external/process.php`
3. `db/services.php`
4. `lang/en/local_json2activity.php`
5. `README.md`
6. Optional: add `classes/external/get_status.php` (recommended clean split)

## 1) Add `assign` support in processor

### File
`classes/processor.php`

### Changes
1. Update supported types:
- from: `['label', 'page', 'url']`
- to: `['label', 'page', 'url', 'assign']`

2. Add `assign` mapping in `build_module_info()`:
- required:
  - `name`
- optional with safe defaults:
  - `intro`, `introformat=FORMAT_HTML`
  - `allowsubmissionsfromdate=0`
  - `duedate=0`
  - `cutoffdate=0`
  - `grade=100`
  - `submissiondrafts=0`
  - `sendnotifications=0`
  - `blindmarking=0`

3. Keep `dry_run` behavior unchanged:
- no call to `add_moduleinfo()` in dry-run,
- item status = `validated`.

## 2) Return canonical nested response shape

### File
`classes/external/process.php`

### Current issue
Flat fields are returned:
- top-level `moodle_request_log_id`, `error_code`, `error_message`
- item-level `error_code`, `error_message`

### Target shape
```json
{
  "request_id": "uuid",
  "courseid": 123,
  "status": "success|partial_success|failed|queued|processing|replayed|rejected",
  "created_count": 2,
  "failed_count": 1,
  "items": [
    {
      "item_id": "i1",
      "status": "created|failed|validated|queued|skipped",
      "type": "label",
      "section": 0,
      "cmid": 12,
      "instanceid": 34,
      "error": { "code": "VALIDATION_ERROR", "message": "..." }
    }
  ],
  "debug": {
    "moodle_request_log_id": 1122,
    "request_debug_url": "https://.../local/json2activity/logs.php?requestid=uuid"
  },
  "error": { "code": "REPLAY_REJECTED", "message": "..." }
}
```

### Mapping rules
1. `partial` -> `partial_success`
2. `dry_run_success` -> `success`
3. `dry_run_partial` -> `partial_success`
4. `dry_run_failed` -> `failed`
5. rejected path stays `rejected`

### Update required functions
1. `format_result()`
2. `format_error()`
3. `execute_returns()` (schema must match nested objects)

## 3) Add WS status function

### New function
`local_json2activity_get_status(requestid, courseid)`

### Implementation
Recommended new file:
- `classes/external/get_status.php`

Behavior:
1. Validate params (`requestid`, `courseid`).
2. Query `local_json2activity_req` by `requestid` (+ optional `courseid` match).
3. Query items from `local_json2activity_item`.
4. Return same nested canonical response shape.
5. Include `debug.request_debug_url`.

### Service registration
File: `db/services.php`
- add function entry for `local_json2activity_get_status`
- include in `JSON2Activity API` service `functions[]`.

## 4) Error code normalization

### Recommendation
Normalize plugin error codes to stable uppercase codes:
- `VALIDATION_ERROR`
- `AUTH_ERROR`
- `REPLAY_REJECTED`
- `TIMESTAMP_REJECTED`
- `SIGNATURE_INVALID`
- `INTERNAL_ERROR`

Keep message human-readable, but code deterministic.

## 5) Logs and debug expectations

Ensure request table remains source of truth for:
- `requestid`, `clientid`, `courseid`, `status`, `mode`, `dryrun`,
- `payloadhash`, `durationms`, `errsummary`.

Every WS response should include:
- `debug.moodle_request_log_id` when available,
- `debug.request_debug_url`.

## 6) Replay behavior

Current plugin rejects replay as error (`rejected`).
Keep this for now; just return nested `error` payload.

## 7) Manual validation checklist

1. Submit WS with `label` only -> `success`, debug link valid.
2. Submit WS with mixed `label/url/page/assign` -> created items correct.
3. Submit invalid item -> `partial_success` with item-level `error`.
4. Submit `dry_run=true` -> no real module created, statuses `validated`.
5. Replay same `request_id` -> `rejected` + `error.code=REPLAY_REJECTED`.
6. Call WS status function on known request -> same nested schema.

## 8) Quick payload sample (with assign)

```json
{
  "schema": "json2activity/v1",
  "request_id": "3d3d8f3b-1b9f-4f7a-9f8b-2e7b11f5c8d1",
  "courseid": 123,
  "dry_run": false,
  "mode": "partial",
  "items": [
    {
      "item_id": "label-1",
      "section": 0,
      "activity": { "type": "label", "data": { "html": "<p>Intro</p>", "visible": true } }
    },
    {
      "item_id": "url-1",
      "section": 1,
      "activity": { "type": "url", "data": { "name": "Doc", "url": "https://example.com", "visible": true } }
    },
    {
      "item_id": "page-1",
      "section": 1,
      "activity": { "type": "page", "data": { "name": "Chapter", "html": "<h2>Chapitre</h2>", "visible": true } }
    },
    {
      "item_id": "assign-1",
      "section": 2,
      "activity": { "type": "assign", "data": { "name": "Homework 1", "intro": "<p>Do it</p>" } }
    }
  ]
}
```
