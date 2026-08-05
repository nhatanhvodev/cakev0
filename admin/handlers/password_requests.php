<?php
/* admin/handlers/password_requests.php - legacy password requests are retired.
 * Auth0 is the credential authority; password reset/change must happen there.
 */

function handle_update_password_request_status(mysqli $conn): void
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setAdminToast("Phien lam viec het han, vui long thu lai.", "error");
        redirectToTab('password-requests');
    }

    setAdminToast("Mat khau hien do Auth0 quan ly. Vui long dung luong dat lai mat khau cua Auth0.", "warning");
    redirectToTab('password-requests');
}
