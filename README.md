# JSON2Activity - Moodle Plugin

A Moodle local plugin that allows creating activities from JSON payloads, either through a web form interface or via a secure REST API.

## Features

- **Multi-activity support**: Create Labels, Pages, and URLs (extensible to other activity types)
- **Web form interface**: Import JSON directly in Moodle's UI
- **REST API**: Secure web service endpoint for external applications
- **Security**: HMAC signature verification, IP allowlist, anti-replay protection
- **Traceability**: Full logging of requests and items for debugging
- **Idempotence**: Request ID-based deduplication

## Installation

1. Copy the plugin folder to `/local/json2activity/`
2. Navigate to Site Administration → Notifications
3. Follow the installation wizard
4. Configure the plugin in Site Administration → Plugins → Local plugins → JSON2Activity

## JSON Schema v1

### Envelope format (for API)

```json
{
  "schema": "json2activity/v1",
  "request_id": "3d3d8f3b-1b9f-4f7a-9f8b-2e7b11f5c8d1",
  "courseid": 123,
  "dry_run": false,
  "mode": "partial",
  "items": [
    {
      "item_id": "intro-001",
      "section": 0,
      "activity": {
        "type": "label",
        "data": {
          "html": "<p>Introduction</p>",
          "visible": true
        }
      }
    }
  ]
}
```

### Legacy format (for form interface)

```json
[
  {
    "section": 0,
    "activity": {
      "type": "label",
      "html": "<p>My label</p>",
      "visible": true
    }
  }
]
```

## Supported Activity Types

### Label
```json
{
  "type": "label",
  "data": {
    "html": "<p>Content</p>",
    "visible": true
  }
}
```

### Page
```json
{
  "type": "page",
  "data": {
    "name": "Page Title",
    "html": "<h1>Content</h1>",
    "visible": true
  }
}
```

### URL
```json
{
  "type": "url",
  "data": {
    "name": "Link Title",
    "url": "https://example.com",
    "visible": true
  }
}
```

## API Security

### Headers

- `X-J2A-Client-Id`: Client identifier
- `X-J2A-Request-Id`: UUID v4 (idempotence key)
- `X-J2A-Timestamp`: Epoch seconds
- `X-J2A-Nonce`: Random string (optional)
- `X-J2A-Signature`: HMAC SHA-256 signature (base64)

### Signature Generation

```
canonical_string = timestamp + "." + request_id + "." + sha256_hex(raw_body)
signature = base64(hmac_sha256(client_secret, canonical_string))
```

## Web Service

### Endpoint

`POST /webservice/rest/server.php?wsfunction=local_json2activity_process`

### Parameters

- `wstoken`: Moodle web service token
- `moodlewsrestformat`: json
- `payload`: JSON payload string
- `clientid`: Client ID (optional for HMAC validation)
- `requestid`: Request ID
- `timestamp`: Epoch seconds
- `nonce`: Nonce (optional)
- `signature`: HMAC signature

## Configuration

Navigate to Site Administration → Plugins → Local plugins → JSON2Activity:

### Security Settings
- Max timestamp skew (seconds)
- Require nonce
- Nonce TTL
- Max payload size
- Max items per request
- Reject script tags
- Store payload mode (full/hash/truncated)
- Store stacktraces

### Processing Settings
- Default processing mode (partial/atomic)
- Async threshold

### Data Retention
- Retain logs (days)

## Capabilities

- `local/json2activity:use`: Use the form interface
- `local/json2activity:viewlogs`: View request logs
- `local/json2activity:managelogs`: Manage logs (purge, view payloads)
- `local/json2activity:execute`: Execute via web service

## Debug Interface

Access request logs at: Site Administration → Plugins → Local plugins → Request Logs

View details for each request including:
- Request metadata
- Full payload (if stored)
- Response
- Individual item status

## License

GNU GPL v3 or later
