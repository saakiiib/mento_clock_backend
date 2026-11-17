# Super Admin employee transfer and deletion

## Transfer between businesses

- The Super Admin can transfer an employee only between active businesses under the same `client_account_id`.
- The source employee row is retained as inactive, using an internal non-login archive email. Existing attendance, rota, leave and HR history remains attached to the source business and employee row.
- A destination employee row is created with the same sign-in email, password hash and employee profile. The source row's active/inactive status is copied. The operator must assign one or more active destination branches.
- Existing app sessions and tokens are revoked. The employee signs in again using the same email and password. The destination business starts a separate operational history.
- Transfers with an open attendance shift are blocked until the shift is corrected or clocked out.
- Both business audit logs record the transfer and the client contact who authorised it.

This preserves the business-scoped attendance foreign keys and prevents old records from silently appearing under the destination business.

## Permanent deletion

- The operator must confirm client authorisation and type the employee's current login email.
- The transaction deletes the employee profile, branch assignments, attendance, rota, leave, employee documents and their files, training, lifecycle records, targeted notices, reminders and employee-linked application events.
- Other employees' references to this employee as a reporting or leave authority are cleared.
- A minimal Super Admin audit entry is retained to record the authorised deletion. This is stated in the confirmation screen and completion message.
- The operation is permanent and has no restore action.

## Deployment

No schema migration is required for these features. Deploy the V24 application code and clear Laravel route/view caches using the normal deployment process.
