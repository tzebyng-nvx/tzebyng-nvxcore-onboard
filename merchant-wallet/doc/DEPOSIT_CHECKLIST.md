- [x] Logged-in user enters an amount (and selects currency / bank as required).

- [x] Validate the input — positive amount, and within the gateway's min/max for that currency.

- [x] Create a transaction record with status pending and a unique merchant order id.

- [x] Call the gateway auth endpoint to obtain a token, then create the order with your redirect_url and callback_url. The gateway returns a payment URL and a payment id.

- [x] Redirect the user to the payment URL to complete payment on the sandbox.

- [x] The gateway sends a server-to-server callback (POST, JSON) to your callback_url.

- [x] The deposit is confirmed by the callback, not by the browser redirect.

- [x] Verify the callback is genuine using the gateway's signature scheme. Reject anything that doesn't match.

- [ ] On a verified completed callback, set the transaction to success and credit the wallet — inside a DB transaction and idempotently (a repeated callback must not credit twice). On failure, mark the transaction failed.

- [ ] The user sees the updated balance and the completed transaction.