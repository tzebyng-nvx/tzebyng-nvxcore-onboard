- [ ] Logged-in user requests a withdrawal — amount, receiver bank, holder name, account number.

- [ ] Validate the amount and check sufficient available balance.

- [ ] Create a withdrawal transaction (pending) and hold/deduct the funds safely — no negative balance, and no double-spend even under concurrent requests (DB transactions + row locking).

- [ ] Call auth, then the withdrawal endpoint with your callback_url.

- [ ] In the sandbox, the payout is manually approved or rejected in the gateway's Back Office. After that, the gateway sends a callback with the result.

- [ ] On a verified callback: mark the transaction success on completion, or failed on rejection and release the held funds back to the balance.