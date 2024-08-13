# Approve links in Nextcloud

Generate approval links in Nextcloud.

If you want to get something approved, you can generate an approve link with this app.
The generated link leads to an approval page with a description and 2 buttons to approve or reject.
Clicking on those buttons will perform a request to the approve or reject callback URI.

You can generate a link with this API endpoint:

POST `/ocs/v2.php/apps/approve_links/api/v1/link`

You must provide the following parameters:

* approveCallbackUri: (string, required) The callback URI to request when the `approve` button is clicked
* rejectCallbackUri: (string, required) The callback URI to request when the `reject` button is clicked
* description: (string, required) The description displayed in the approval page
