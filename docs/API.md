# AeroTech API route list

The API is versioned under `/api/v1`. All routes other than `POST /auth/token` require a Sanctum personal access token:

```http
Authorization: Bearer <token>
Accept: application/json
```

Obtain a token with `POST /api/v1/auth/token`, sending `email`, `password`, and an optional `device_name`. The response contains `token` and the authenticated `user` with its roles and permissions. Sign out using `DELETE /api/v1/auth/token`; this revokes the current bearer token.

Laravel validation failures return HTTP `422` with an `errors` object. Unauthenticated requests return `401`, and authenticated requests without permission return `403`. Resource responses generally use a `data` object or collection; paginated endpoints use Laravel's paginator shape. Roles and permission checks are enforced by the API, independently of frontend route visibility.

## Authentication and reference data

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/auth/token` | Issue a rate-limited Sanctum token |
| `GET` | `/auth/me` | Current user, roles, and permissions |
| `DELETE` | `/auth/token` | Revoke current token |
| `GET` | `/dashboard` | Operational dashboard summary |
| `GET` | `/notifications` | Current user's notifications |
| `POST` | `/notifications/{notificationId}/read` | Mark a notification read |
| `GET` | `/directory/users` | User directory |
| `GET` | `/directory/locations` | Locations, including storage locations |
| `GET` | `/directory/suppliers` | Supplier directory |
| `GET` | `/asset-categories` | Asset categories |

## Procurement and asset register

| Method | Path | Purpose |
| --- | --- | --- |
| `GET`, `POST` | `/purchase-requests` | List or submit a purchase request |
| `GET` | `/purchase-requests/{purchase_request}` | View a purchase request |
| `POST` | `/purchase-requests/{purchaseRequest}/decision` | Approve or reject at the current approval stage; rejection requires a reason |
| `POST` | `/purchase-requests/{purchaseRequest}/resubmissions` | Resubmit a rejected request with feedback addressed |
| `GET`, `POST` | `/purchase-orders` | List or record purchase orders |
| `POST` | `/purchase-orders/{purchaseOrder}/receiving` | Receive PO units and automatically create asset records |
| `GET` | `/assets` | Search/list assets |
| `GET` | `/assets/{asset}` | View asset details and audit history |
| `GET` | `/my-assets` | Current user's assigned assets |
| `GET` | `/reports/assets.csv` | Download asset register CSV |
| `GET` | `/reports/assets.pdf` | Download asset register PDF |

## Assignment and transfers

| Method | Path | Purpose |
| --- | --- | --- |
| `GET`, `POST` | `/asset-requests` | List authorized requests or submit an equipment request |
| `POST` | `/asset-requests/{assetRequest}/assignments` | Check policy and assign an available matching asset; courier handoff requires evidence |
| `POST` | `/assignments/{assetAssignment}/acknowledgement` | Assigned employee acknowledges the accountability form |
| `POST` | `/assignments/{assetAssignment}/check-in` | Assignee or asset technician checks in an active assignment |
| `GET` | `/assignments/{assetAssignment}/accountability-form` | Download printable accountability form PDF |
| `GET` | `/assignments/{assetAssignment}/evidence` | Authorized access to handoff evidence |
| `GET`, `POST` | `/transfers` | List authorized transfers or request a permanent/temporary transfer |
| `POST` | `/transfers/{assetTransfer}/approval` | Approve a transfer |
| `POST` | `/transfers/{assetTransfer}/acceptance` | Destination user accepts the transfer and location change |

Temporary transfer requests provide `loan_started_at`, `due_at`, and optionally `replacement_expected_at`. The service rejects a loan longer than one month or a due date beyond the expected replacement date.

## Maintenance and repair

| Method | Path | Purpose |
| --- | --- | --- |
| `GET`, `POST` | `/maintenance-tickets` | List authorized tickets or report an incident |
| `PUT` | `/maintenance-tickets/{maintenanceTicket}/checklist` | Save the standard assessment checklist |
| `POST` | `/maintenance-tickets/{maintenanceTicket}/repairs` | Record repair details and costs |
| `POST` | `/repairs/{repair}/cost-approval` | Approve paid repair costs |
| `POST` | `/repairs/{repair}/completion` | Complete a repair and update asset condition/status |

Warranty coverage is determined from the asset's stored warranty expiry. Repair records preserve the cost and timestamps used by repair KPIs.

## Software licenses

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/licenses` | License inventory and seat counts |
| `GET`, `POST` | `/license-requests` | List authorized requests or submit a license request |
| `POST` | `/license-requests/{licenseRequest}/decision` | Manager decision |
| `POST` | `/license-requests/{licenseRequest}/record` | Purchasing records an approved license |
| `POST` | `/licenses/{softwareLicense}/assignments` | Assign an available seat to a user or asset |
| `DELETE` | `/licenses/{softwareLicense}/assignments/{licenseAssignment}` | Revoke a seat assignment |
| `POST` | `/licenses/compliance-checks` | Run and persist a compliance check |
| `GET` | `/licenses/compliance-checks` | Review compliance-check history |
| `GET` | `/licenses/renewal-reviews` | List pending IT/management renewal reviews |
| `POST` | `/licenses/renewal-reviews/{licenseRenewalReview}` | Record the current review stage |

Assignment is transactionally seat-limited; requests that would exceed purchased seats are rejected. Renewal reminders are scheduled at 90, 60, and 30 days before expiry.

## Lifecycle and disposal

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/lifecycle/reviews` | List assets due for lifecycle review |
| `POST` | `/lifecycle/reviews/scan` | Run a lifecycle scan using category rules and repair history |
| `GET`, `POST` | `/disposals` | List authorized disposal requests or create one |
| `POST` | `/disposals/{disposal}/finance-approval` | Finance approval |
| `POST` | `/disposals/{disposal}/management-approval` | Management approval |
| `POST` | `/disposals/{disposal}/completion` | Record data wipe, file removal, certificate, and recipient/vendor |
| `GET` | `/disposals/{disposal}/certificate` | Download the stored destruction certificate |

## Implementation notes

- Request payload validation is enforced in Laravel form requests or controller validation. See the corresponding endpoint/controller for required fields and accepted values.
- File uploads (handoff evidence and destruction certificates) are validated and stored on the private local disk. Use an appropriately protected production storage disk for deployment.
- Scheduled reminders are queued. Run a queue worker and configure the Laravel scheduler to deliver them.
- This route list documents the current implementation, not a guarantee of external HR, procurement, or LDAP connectivity.
