# Changelog

## 1.0.0 (unreleased)

- Record ActiveRecord inserts, updates and deletes with old and new values per attribute.
- Record write requests and requests that changed data, with masked parameters and status codes.
- Track models per behavior or all models with an exclude list.
- Store audit rows in a dedicated database on the same server, inside the caller's transaction.
- Add changes grid, requests grid and request detail page behind host-defined access rules.
- Add the `cleanup` console command.
- Add pt-PT translations.
