<?php
session_start();

if (!isset($_SESSION['a_id'])) {
    header("Location: ../login.php");
    exit();
}

include '../db/db_conn.php';

// Fetch admin's ID from the session
$adminId = $_SESSION['a_id']; 

// Fetch admin info
$sql = "SELECT a_id, firstname, middlename, lastname, birthdate, email, role, department, phone_number, address, pfp FROM admin_register WHERE a_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$adminInfo = $result->fetch_assoc();

// Fetch all leave requests that have been approved or denied by the supervisor
$sql = "SELECT lr.leave_id, e.e_id, e.firstname, e.lastname, e.department, lr.start_date, lr.end_date, lr.leave_type, lr.proof, lr.status, lr.created_at
        FROM leave_requests lr
        JOIN employee_register e ON lr.e_id = e.e_id
        WHERE lr.supervisor_approval = 'Supervisor Approved' AND lr.status = 'Supervisor Approved' ORDER BY created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

// Fetch holidays from the database
$holidays = [];
$holiday_sql = "SELECT date FROM non_working_days";
$holiday_stmt = $conn->prepare($holiday_sql);
$holiday_stmt->execute();
$holiday_result = $holiday_stmt->get_result();
while ($holiday_row = $holiday_result->fetch_assoc()) {
    $holidays[] = $holiday_row['date']; 
}

// Handle approve/deny actions by the admin
if (isset($_GET['leave_id']) && isset($_GET['status'])) {
    $leave_id = $_GET['leave_id'];
    $status = $_GET['status'];

    // Validate the status input
    if (!in_array($status, ['approve', 'deny'])) {
        header("Location: ../admin/leave_requests.php?status=invalid_status");
        exit();
    }

// Fetch the specific leave request
$sql = "SELECT e.department, e.e_id, e.firstname, e.lastname, lr.start_date, lr.end_date, lr.leave_type, lr.proof, lr.status, e.gender,
               el.bereavement_leave, el.emergency_leave, el.maternity_leave, el.mcw_special_leave, el.parental_leave,
               el.service_incentive_leave, el.sick_leave, el.vacation_leave, el.vawc_leave, el.bereavement_leave_male,
               el.emergency_leave_male, el.parental_leave_male, el.paternity_leave_male, el.service_incentive_leave_male,
               el.sick_leave_male, el.vacation_leave_male
        FROM leave_requests lr
        JOIN employee_register e ON lr.e_id = e.e_id
        JOIN employee_leaves el ON el.employee_id = e.e_id
        WHERE lr.leave_id = ?";
$action_stmt = $conn->prepare($sql);
$action_stmt->bind_param("i", $leave_id);
$action_stmt->execute();
$action_result = $action_stmt->get_result();

if ($action_result->num_rows > 0) {
    $row = $action_result->fetch_assoc();
    
    // Determine which leave type column to use based on gender and leave_type
    $leave_type = $row['leave_type'];
    $gender = $row['gender'];
    
    switch ($leave_type) {
        case 'Bereavement Leave':
            $available_balance = ($gender === 'Male') ? $row['bereavement_leave_male'] : $row['bereavement_leave'];
            break;
        case 'Emergency Leave':
            $available_balance = ($gender === 'Male') ? $row['emergency_leave_male'] : $row['emergency_leave'];
            break;
        case 'Maternity Leave':
            $available_balance = $row['maternity_leave'];
            break;
        case 'MCW Special Leave':
            $available_balance = $row['mcw_special_leave'];
            break;
        case 'Parental Leave':
            $available_balance = ($gender === 'Male') ? $row['parental_leave_male'] : $row['parental_leave'];
            break;
        case 'Paternity Leave':
            $available_balance = $row['paternity_leave_male']; // For male employees only
            break;
        case 'Service Incentive Leave':
            $available_balance = ($gender === 'Male') ? $row['service_incentive_leave_male'] : $row['service_incentive_leave'];
            break;
        case 'Sick Leave':
            $available_balance = ($gender === 'Male') ? $row['sick_leave_male'] : $row['sick_leave'];
            break;
        case 'Vacation Leave':
            $available_balance = ($gender === 'Male') ? $row['vacation_leave_male'] : $row['vacation_leave'];
            break;
        case 'VAWC Leave':
            $available_balance = $row['vawc_leave'];
            break;
        default:
            $available_balance = 0; // Default to 0 if leave_type is not recognized
            break;
    }

    $start_date = $row['start_date'];
    $end_date = $row['end_date'];

    // Calculate total leave days excluding Sundays and holidays
    $leave_days = 0;
    $current_date = strtotime($start_date);

    while ($current_date <= strtotime($end_date)) {
        $current_date_str = date('Y-m-d', $current_date);
        if (date('N', $current_date) != 7 && !in_array($current_date_str, $holidays)) {
            $leave_days++;
        }
        $current_date = strtotime("+1 day", $current_date);
    }

    if ($status === 'approve') {
        if ($leave_days > $available_balance) {
            header("Location: ../admin/leave_requests.php?status=insufficient_balance");
            exit();
        } else {
            $new_balance = $available_balance - $leave_days;

            // Prepare the update query based on the leave type
            switch ($leave_type) {
                case 'Bereavement Leave':
                    $balance_field = ($gender === 'Male') ? 'bereavement_leave_male' : 'bereavement_leave';
                    break;
                case 'Emergency Leave':
                    $balance_field = ($gender === 'Male') ? 'emergency_leave_male' : 'emergency_leave';
                    break;
                case 'Maternity Leave':
                    $balance_field = 'maternity_leave';
                    break;
                case 'MCW Special Leave':
                    $balance_field = 'mcw_special_leave';
                    break;
                case 'Parental Leave':
                    $balance_field = ($gender === 'Male') ? 'parental_leave_male' : 'parental_leave';
                    break;
                case 'Paternity Leave':
                    $balance_field = 'paternity_leave_male'; // Only for male employees
                    break;
                case 'Service Incentive Leave':
                    $balance_field = ($gender === 'Male') ? 'service_incentive_leave_male' : 'service_incentive_leave';
                    break;
                case 'Sick Leave':
                    $balance_field = ($gender === 'Male') ? 'sick_leave_male' : 'sick_leave';
                    break;
                case 'Vacation Leave':
                    $balance_field = ($gender === 'Male') ? 'vacation_leave_male' : 'vacation_leave';
                    break;
                case 'VAWC Leave':
                    $balance_field = 'vawc_leave';
                    break;
                default:
                    $balance_field = ''; // Default to an empty field
                    break;
            }

            $update_sql = "UPDATE leave_requests lr 
                           JOIN employee_register e ON lr.e_id = e.e_id
                           JOIN employee_leaves el ON el.employee_id = e.e_id
                           SET lr.status = 'Approved', lr.admin_approval = 'Admin Approved', lr.admin_id = ?, el.{$balance_field} = ? 
                           WHERE lr.leave_id = ?";

            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iii", $adminId, $new_balance, $leave_id);

            if ($update_stmt->execute()) {
                // Log the activity for approval
                $action_type = "Leave Request Approved";
                $affected_feature = "Leave Information";
                $details = "Leave request from {$row['firstname']} {$row['lastname']} ({$row['e_id']}) has been approved | Leave day(s): $leave_days.";
                log_activity($adminId, $action_type, $affected_feature, $details);
            
                // Notify the employee
                $employeeId = $row['e_id']; // Get the employee's ID
                $message = "Your leave request has been approved. Leave days: $leave_days.";
                $notificationSql = "INSERT INTO notifications (employee_id, message) VALUES (?, ?)";
                $notificationStmt = $conn->prepare($notificationSql);
                $notificationStmt->bind_param("is", $employeeId, $message);
                $notificationStmt->execute();
            
                header("Location: ../admin/leave_requests.php?status=approved");
            } else {
                error_log("Error updating leave balance: " . $conn->error);
                header("Location: ../admin/leave_requests.php?status=error");
            }
        }
    } elseif ($status === 'deny') {
        // Check if admin_comments is provided and not empty
        if (isset($_GET['admin_comments']) && !empty($_GET['admin_comments'])) {
            $admin_comments = $_GET['admin_comments'];
    
            // Deny the leave request and add the admin's comment
            $deny_sql = "UPDATE leave_requests 
                         SET status = 'Denied', 
                             admin_approval = 'Admin Denied', 
                             admin_id = ?, 
                             admin_comments = ? 
                         WHERE leave_id = ?";
            $deny_stmt = $conn->prepare($deny_sql);
            $deny_stmt->bind_param("isi", $adminId, $admin_comments, $leave_id);
        } else {
            // Deny the leave request without comments
            $deny_sql = "UPDATE leave_requests 
                         SET status = 'Denied', 
                             admin_approval = 'Admin Denied', 
                             admin_id = ? 
                         WHERE leave_id = ?";
            $deny_stmt = $conn->prepare($deny_sql);
            $deny_stmt->bind_param("ii", $adminId, $leave_id);
        }
    
        // Execute the prepared statement
        if ($deny_stmt->execute()) {
            // Log the activity for admin denial
            $action_type = "Leave Request Denied";
            $affected_feature = "Leave Information";
            $details = "Leave request from {$row['firstname']} {$row['lastname']} ({$row['e_id']}) has been denied by the admin.";
            log_activity($adminId, $action_type, $affected_feature, $details);
        
            // Notify the employee
            $employeeId = $row['e_id']; // Get the employee's ID
            $message = "Your leave request has been denied.";
            if (!empty($admin_comments)) {
                $message .= " Reason: " . $admin_comments;
            }
            $notificationSql = "INSERT INTO notifications (employee_id, message) VALUES (?, ?)";
            $notificationStmt = $conn->prepare($notificationSql);
            $notificationStmt->bind_param("is", $employeeId, $message);
            $notificationStmt->execute();
        
            // Redirect to admin's leave request page
            header("Location: ../admin/leave_requests.php?status=success");
        } else {
            header("Location: ../admin/leave_requests.php?status=error");
        }
    }
} else {
    header("Location: ../admin/leave_requests.php?status=not_found");
}
exit();

}

// Function to log activity
function log_activity($adminId, $action_type, $affected_feature, $details) {
    global $conn;

    // Get the admin's name
    $admin_query = "SELECT firstname, lastname FROM admin_register WHERE a_id = ?";
    $admin_stmt = $conn->prepare($admin_query);
    $admin_stmt->bind_param("i", $adminId);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    $admin = $admin_result->fetch_assoc();
    $admin_name = $admin['firstname'] . ' ' . $admin['lastname'];

    // Capture admin's IP address
    $ip_address = $_SERVER['REMOTE_ADDR'];

    // Insert the log entry into activity_logs table
    $log_query = "INSERT INTO activity_logs (admin_id, admin_name, action_type, affected_feature, details, ip_address) 
                  VALUES (?, ?, ?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("isssss", $adminId, $admin_name, $action_type, $affected_feature, $details, $ip_address);
    $log_stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <link rel="icon" type="image/png" href="../img/logo.png">
    <title>Leave Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' /> <!-- calendar -->
    <link href="../css/styles.css" rel="stylesheet" />
    <link href="../css/calendar.css" rel="stylesheet"/>
    <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
</head>

<body class="sb-nav-fixed bg-black">
    <?php include 'navbar.php'; ?>
    <div id="layoutSidenav">
        <?php include 'sidebar.php'; ?>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid position-relative px-4">
                    <h1 class="mb-4 text-light">Leave Requests</h1>
                    <div class="container-fluid" id="calendarContainer"
                        style="position: fixed; top: 7%; right: 40; z-index: 1050;
                        max-width: 100%; display: none;">
                        <div class="row">
                            <div class="col-md-9 mx-auto">
                                <div id="calendar" class="p-2"></div>
                            </div>
                        </div>
                    </div>
                    <div class="container">
                        <?php if (isset($_GET['status'])): ?>
                            <div id="status-alert" class="alert
                                <?php if ($_GET['status'] === 'success'): ?>
                                    alert-success
                                <?php elseif ($_GET['status'] === 'error'): ?>
                                    alert-danger
                                <?php elseif ($_GET['status'] === 'not_exist'): ?>
                                    alert-warning
                                <?php elseif ($_GET['status'] === 'insufficient_balance'): ?>
                                    alert-warning
                                <?php endif; ?>" role="alert">
                                <?php if ($_GET['status'] === 'success'): ?>
                                    Leave request status updated successfully.
                                <?php elseif ($_GET['status'] === 'error'): ?>
                                    Error updating leave request status. Please try again.
                                <?php elseif ($_GET['status'] === 'not_exist'): ?>
                                    The leave request ID does not exist or could not be found.
                                <?php elseif ($_GET['status'] === 'insufficient_balance'): ?>
                                    Insufficient leave balance. The request cannot be approved.
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card mb-4 bg-dark text-light">
                        <div class="card-header border-bottom border-1 border-secondary">
                            <i class="fas fa-table me-1"></i>
                            Pending Request
                        </div>
                        <div class="card-body">
                            <table id="datatablesSimple" class="table text-light text-center">
                                <thead>
                                    <tr>
                                        <th>Requested On</th>
                                        <th>Employee ID</th>
                                        <th>Employee Name</th>
                                        <th>Department</th>
                                        <th>Duration of Leave</th>
                                        <th>Deduction Leave</th>
                                        <th>Reason</th>
                                        <th>Proof</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result->num_rows > 0): ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <?php
                                                // Calculate total leave days excluding Sundays and holidays
                                                $leave_days = 0;
                                                $current_date = strtotime($row['start_date']);
                                                $end_date = strtotime($row['end_date']);

                                                while ($current_date <= $end_date) {
                                                $current_date_str = date('Y-m-d', $current_date);
                                                // Check if the current day is not a Sunday (0 = Sunday) and not a holiday
                                                if (date('N', $current_date) != 7 && !in_array($current_date_str, $holidays)) {
                                                        $leave_days++; // Count this day as a leave day
                                                }
                                                $current_date = strtotime("+1 day", $current_date); // Move to the next day
                                                }
                                            ?>
                                        <tr>
                                        <td>
                                            <?php
                                                if (isset($row['created_at'])) {
                                                    echo htmlspecialchars(date("F j, Y", strtotime($row['created_at']))) . ' <span class="text-warning"> | </span> ' . htmlspecialchars(date("g:i A", strtotime($row['created_at'])));
                                                } else {
                                                    echo "Not Available";
                                                }
                                            ?>
                                        </td>
                                            <td><?php echo htmlspecialchars($row['e_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                                            <td><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td><?php echo htmlspecialchars(date("F j, Y", strtotime($row['start_date']))) . ' <span class="text-warning"> | </span> ' . htmlspecialchars(date("F j, Y", strtotime($row['end_date']))); ?></td>
                                            <td><?php echo htmlspecialchars($leave_days); ?> day/s</td>
                                            <td><?php echo htmlspecialchars($row['leave_type']); ?></td>
                                            <td>
                                                <?php if (!empty($row['proof'])): ?>
                                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#proofModal<?php echo $row['proof']; ?>">View</button>
                                                <?php else: ?>
                                                    No proof provided
                                                <?php endif; ?>
                                            </td>
                                            <div class="modal fade" id="proofModal<?php echo $row['proof']; ?>" tabindex="-1" aria-labelledby="proofModalLabel<?php echo $row['proof']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content bg-dark text-light" style="width: 600px; height: 500px;">
                                                        <div class="modal-header border-bottom border-secondary">
                                                            <h5 class="modal-title" id="proofModalLabel<?php echo $row['proof']; ?>">Proof of Leave</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body d-flex align-items-center justify-content-center" style="overflow-y: auto; height: calc(100% - 80px);">
                                                            <div id="proofCarousel<?php echo $row['proof']; ?>" class="carousel slide d-flex align-items-center justify-content-center" data-bs-ride="false">
                                                                <div class="carousel-inner">
                                                                    <?php
                                                                        // Assuming proof field contains a comma-separated list of file names
                                                                        $filePaths = explode(',', $row['proof']);
                                                                        $isActive = true;  // To set the first item as active
                                                                        $fileCount = count($filePaths);  // Count the number of files
                                                                        $baseURL = 'http://localhost/HR2/proof/';  // Define the base URL for file access

                                                                        foreach ($filePaths as $filePath) {
                                                                            $filePath = trim($filePath);  // Clean the file path
                                                                            $fullFilePath = $baseURL . $filePath;  // Construct the full URL for the file
                                                                            $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

                                                                            // Check if the file is an image (e.g., jpg, jpeg, png, gif)
                                                                            $imageTypes = ['jpg', 'jpeg', 'png', 'gif'];
                                                                            if (in_array(strtolower($fileExtension), $imageTypes)) {
                                                                                echo '<div class="carousel-item ' . ($isActive ? 'active' : '') . '">';
                                                                                echo '<img src="' . htmlspecialchars($fullFilePath) . '" alt="Proof of Leave" class="d-block w-100" style="max-height: 400px; object-fit: contain;">';
                                                                                echo '</div>';
                                                                                $isActive = false;
                                                                            }
                                                                            // Check if the file is a PDF (this will just show an embed for PDFs)
                                                                            elseif (strtolower($fileExtension) === 'pdf') {
                                                                                echo '<div class="carousel-item ' . ($isActive ? 'active' : '') . '">';
                                                                                echo '<embed src="' . htmlspecialchars($fullFilePath) . '" type="application/pdf" width="100%" height="400px" />';
                                                                                echo '</div>';
                                                                                $isActive = false;
                                                                            }
                                                                            // Handle other document types (e.g., docx, txt) – just provide a link to view the document
                                                                            else {
                                                                                echo '<div class="carousel-item ' . ($isActive ? 'active' : '') . '">';
                                                                                echo '<a href="' . htmlspecialchars($fullFilePath) . '" target="_blank" class="btn btn-primary">View Document</a>';
                                                                                echo '</div>';
                                                                                $isActive = false;
                                                                            }
                                                                        }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Check if there's only one file; if yes, hide the carousel controls -->
                                                        <?php if ($fileCount > 1): ?>
                                                            <button class="carousel-control-prev btn btn-secondary position-absolute top-50 start-0 translate-middle-y w-auto" type="button" data-bs-target="#proofCarousel<?php echo $row['proof']; ?>" data-bs-slide="prev">
                                                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                                <span class="visually-hidden">Previous</span>
                                                            </button>
                                                            <button class="carousel-control-next btn btn-secondary position-absolute top-50 end-0 translate-middle-y w-auto" type="button" data-bs-target="#proofCarousel<?php echo $row['proof']; ?>" data-bs-slide="next">
                                                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                                <span class="visually-hidden">Next</span>
                                                            </button>
                                                        <?php endif; ?>
                                                        <div class="modal-footer border-top border-secondary">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center mb-0">
                                                <button class="btn btn-success btn-sm me-2" onclick="confirmAction('approve', <?php echo $row['leave_id']; ?>)">Approve</button>
                                                <button class="btn btn-danger btn-sm" onclick="confirmAction('deny', <?php echo $row['leave_id']; ?>)">Deny</button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
                <div class="modal fade" id="denyReasonModal" tabindex="-1" aria-labelledby="denyReasonModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light">
                            <div class="modal-header">
                                <h5 class="modal-title" id="denyReasonModalLabel">Reason for Denial</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <textarea id="denyReason" class="form-control" placeholder="Enter reason for denial..." rows="3"></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" onclick="submitDeny()">Submit</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="approveConfirmationModal" tabindex="-1" aria-labelledby="approveConfirmationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light">
                            <div class="modal-header">
                                <h5 class="modal-title" id="approveConfirmationModalLabel">
                                    <i class="fa fa-info-circle text-light me-2 fs-4"></i> Confirm Approval
                                </h5>
                                <button type="button" class="btn-close text-light" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body align-items-center">
                                Are you sure you want to approve this leave request?
                                <div class="d-flex justify-content-center mt-3">
                                    <button type="button" class="btn btn-success" onclick="proceedWithApproval()">Yes</button>
                                    <button type="button" class="btn btn-secondary ms-2" data-bs-dismiss="modal">No</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light">
                            <div class="modal-header">
                                <h5 class="modal-title" id="confirmationModalLabel">Confirm Denial</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to deny this leave request?
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" onclick="proceedWithDenial()">Yes, Deny</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-light">
                            <div class="modal-header border-bottom border-secondary">
                                <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to log out?
                            </div>
                            <div class="modal-footer border-top border-secondary">
                                <button type="button" class="btn border-secondary text-light" data-bs-dismiss="modal">Cancel</button>
                                <form action="../admin/logout.php" method="POST">
                                    <button type="submit" class="btn btn-danger">Logout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>
<script>
    // Automatically hide the alert after 10 seconds (10,000 milliseconds)
    setTimeout(function() {
        var alertElement = document.getElementById('status-alert');
        if (alertElement) {
            alertElement.style.transition = "opacity 1s ease"; // Add transition for smooth fade-out
            alertElement.style.opacity = 0; // Set the opacity to 0 (fade out)

            setTimeout(function() {
                alertElement.remove(); // Remove the element from the DOM after fade-out
            }, 1000); // Wait 1 second after fade-out to remove the element completely
        }
    }, 5000); // 10 seconds delay

    let currentLeaveId = null; // Variable to store the leave ID
    let denyReason = ''; // Variable to store the denial reason

    function confirmAction(action, leaveId) {
        currentLeaveId = leaveId; // Store the leave ID
        if (action === 'approve') {
            // Show the approval confirmation modal
            const approveModal = new bootstrap.Modal(document.getElementById('approveConfirmationModal'));
            approveModal.show();
        } else if (action === 'deny') {
            // Show the deny reason modal
            const denyReasonModal = new bootstrap.Modal(document.getElementById('denyReasonModal'));
            denyReasonModal.show();
        }
    }

    function proceedWithApproval() {
        // Send the approval request to the server
        fetch(`leave_requests.php?leave_id=${currentLeaveId}&status=approve`, {
            method: 'GET',
        })
        .then(response => {
            if (response.ok) {
                location.reload(); // Reload the page to reflect changes
            } else {
                alert('Failed to approve leave request.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request.');
        });

        // Close the approval modal
        const approveModal = bootstrap.Modal.getInstance(document.getElementById('approveConfirmationModal'));
        approveModal.hide();
    }

    function submitDeny() {
        denyReason = document.getElementById('denyReason').value;

        if (!denyReason) {
            alert('Please enter a reason for denial.');
            return;
        }

        // Hide the reason modal
        const denyReasonModal = bootstrap.Modal.getInstance(document.getElementById('denyReasonModal'));
        denyReasonModal.hide();

        // Show the custom confirmation modal
        const confirmationModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
        confirmationModal.show();
    }

    function proceedWithDenial() {
        // Send the data to the server
        fetch(`leave_requests.php?leave_id=${currentLeaveId}&status=deny&admin_comments=${encodeURIComponent(denyReason)}`, {
            method: 'GET',
        })
        .then(response => {
            if (response.ok) {
                location.reload(); // Reload the page to reflect changes
            } else {
                alert('Failed to deny leave request.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while processing your request.');
        });

        // Close the confirmation modal
        const confirmationModal = bootstrap.Modal.getInstance(document.getElementById('confirmationModal'));
        confirmationModal.hide();
    }
</script>
<!-- Only keep the latest Bootstrap 5 version -->
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
<script src="../js/datatables-simple-demo.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'> </script>
<script src="../js/admin.js"></script>
</body>
</html>
