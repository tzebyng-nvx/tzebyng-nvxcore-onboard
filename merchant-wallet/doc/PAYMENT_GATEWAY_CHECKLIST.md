- [x] Gateway credentials (merchant username, api_key, secret_key, base URL) must be stored in the database (e.g. a settings / gateway table) and read at runtime.

- [x] They must not be hard-coded in .env or config files.

- [x] A single shared sandbox account is used, so no per-tenant credential scoping is required — but structure it so per-tenant credentials would be a small change, not a rewrite.