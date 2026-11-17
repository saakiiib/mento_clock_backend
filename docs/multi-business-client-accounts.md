# Multi-business client accounts

This update adds a parent client account above the existing business tenant model:

```text
Client account (one administrator login)
├── Business A (own modules, timezone and limits)
│   └── Workspaces / branches (limited by the business allocation)
└── Business B (own modules, timezone and limits)
    └── Workspaces / branches (limited by the business allocation)
```

## Super Admin setup

1. Open **Platform → Client accounts → Create client account**.
2. Enter the client contact and one client administrator login.
3. Add each business and set its maximum workspace/branch count and active employee limit per workspace.
4. Choose the product modules. They are enabled on each business created in this form.
5. Share the administrator credentials securely. The password is hashed and is not emailed.
6. Use the account detail page to add further businesses. Use each business's existing client detail page to review/change its limits and settings.

The client signs in once. In the web portal, the business selector in the top bar switches their active business. Workspaces, employees, rotas, attendance, holidays and reports remain scoped to the selected business.

## Manager API business selection

Manager login and `/api/v1/me` include a `businesses` array for grouped client administrators. The manager app must show a business picker when that array contains multiple entries and send the selected business ID on every manager request:

```http
X-Business-ID: 123
```

The server validates that the selected business belongs to the signed-in user's client account and is active. Employees cannot use this header to switch businesses. Calling `/api/v1/manager/businesses` returns each business's workspace and employee allocations and indicates the selected business.

## Existing clients

The migration creates a one-business client account for each existing business and links its existing business administrator. Existing employee accounts, records, quotas and module entitlements are preserved. These clients can continue to sign in as before; Super Admin can set up a new grouped account for clients that need multiple businesses.

## Deployment

Deploy the full Laravel backend release and run the normal production migration command:

```sh
php artisan migrate --force
```

The manager app needs its business picker/header update before one set of credentials can switch businesses inside the app. The web portal selector is included here.
