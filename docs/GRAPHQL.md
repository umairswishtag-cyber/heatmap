# GraphQL API

The endpoint is `POST /graphql`. Send JSON with `query` and optional `variables`. Authenticated operations require `Authorization: Bearer <token>`.

## Create an account

```graphql
mutation Register($input: RegisterInput!) {
  register(input: $input) { token user { id name email } }
}
```

Variables:

```json
{
  "input": {
    "name": "Ada Owner",
    "email": "ada@example.com",
    "password": "a-long-secure-password",
    "password_confirmation": "a-long-secure-password"
  }
}
```

## Create and list projects

```graphql
mutation CreateProject($input: CreateProjectInput!) {
  createProject(input: $input) { id name public_key domains { domain } }
}
```

```graphql
query Projects {
  projects { id name public_key recording_enabled sampling_rate last_event_at domains { domain } }
}
```

Domains are normalized to hostnames. The public key identifies ingestion traffic but is not treated as a secret; server-side origin validation is mandatory.

## Read sessions and replay events

```graphql
query Sessions($projectId: ID!) {
  sessions(projectId: $projectId) {
    id session_uuid started_at duration landing_page device_type browser
    visitor { visitor_uuid }
  }
}
```

```graphql
query Replay($id: ID!) {
  session(id: $id) { id landing_page duration page_count }
  recordingEvents(sessionId: $id)
}
```

Both operations resolve ownership through the authenticated account. A session ID belonging to another customer is never returned.

## Ingestion

```graphql
mutation Ingest($input: RecordingBatchInput!) {
  ingestRecording(input: $input) { accepted batchId eventCount }
}
```

The tracker supplies the project key, browser identity, current page metadata, and either a JSON payload or base64 gzip payload. The API accepts at most 5 MB decompressed and 5,000 event envelopes per mutation. The browser `Origin` hostname must match an allowed project domain. Accepted work is processed asynchronously on the `recordings` queue.
