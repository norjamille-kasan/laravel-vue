---
paths:
  - 'database/migrations/**, app/Models/**'
---

# Models

## Add a `uuid` column on every table; route-model binding uses `uuid`
Every table has an auto-increment `id` PK (existing convention) AND a `uuid` column with a unique index. Models auto-generate `uuid` in a `creating` hook if empty, and override `getRouteKeyName()` to return `'uuid'`. Use `uuid` in URLs, route-model binding, Wayfinder helpers, and any external API reference (share/invite links, delete references). Internal FKs and joins use `id` (bigint). Never expose `id` in user-facing identifiers.
