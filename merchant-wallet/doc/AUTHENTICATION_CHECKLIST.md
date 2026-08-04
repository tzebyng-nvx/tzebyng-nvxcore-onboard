# Authentication Checklist

- [ ] Two different authenticatable models: User (a player) and Admin. Separate tables, both in the tenant database.

- [ ] Two different guards: api → User, admin → Admin. Both JWT.

- [ ] Two separate route files — player routes and admin routes.

- [ ] Login for both. A registration UI for players is optional (bonus).

- [ ] Protected routes are inaccessible without a valid token of the correct type.

You must be able to demonstrate all four of these:
Token	Route	Expected
player	player route	200
player	admin route	401 / 403
admin	admin route	200
admin	player route	401 / 403
