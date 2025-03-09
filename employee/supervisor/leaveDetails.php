<?php
session_start();
include '../../db/db_conn.php';

if (!isset($_SESSION['e_id']) || !isset($_SESSION['position']) || $_SESSION['position'] !== 'Supervisor') {
    header("Location: ../../login.php");
    exit();
}

// Fetch employee's leave data
$employee_id = $_SESSION['e_id']; // Assuming the employee ID is stored in session

// Query to fetch employee's info from the employee_register table
$query_employee = "SELECT * FROM employee_register WHERE e_id = ?";
$stmt_employee = $conn->prepare($query_employee);
$stmt_employee->bind_param("i", $employee_id);
$stmt_employee->execute();
$result_employee = $stmt_employee->get_result();

$employee_data = $result_employee->fetch_assoc();

// Fetch the employee's gender from the employee_register table
$employee_gender = $employee_data['gender']; // Assuming gender is stored in employee_register

// Fetch employee's leave data
$query_leave = "SELECT * FROM employee_leaves WHERE employee_id = ?";
$stmt_leave = $conn->prepare($query_leave);
$stmt_leave->bind_param("i", $employee_id);
$stmt_leave->execute();
$result_leave = $stmt_leave->get_result();

$leave_data = $result_leave->fetch_assoc();

// Fetch the leave requests that are approved for the employee
$query_approved_leave = "SELECT * FROM leave_requests WHERE e_id = ? AND status = 'approved' ORDER BY start_date DESC LIMIT 1"; // Only get the most recent approved leave
$stmt_approved_leave = $conn->prepare($query_approved_leave);
$stmt_approved_leave->bind_param("i", $employee_id);
$stmt_approved_leave->execute();
$result_approved_leave = $stmt_approved_leave->get_result();

if ($result_approved_leave->num_rows > 0) {
    $approved_leave_data = $result_approved_leave->fetch_assoc(); // Fetch the most recent approved leave

    // Get the start and end date of the leave
    $leave_start_date = new DateTime($approved_leave_data['start_date']);
    $leave_end_date = new DateTime($approved_leave_data['end_date']);
    $current_date = new DateTime(); // Current date and time

    // Calculate the total duration of the leave (in days)
    $leave_duration = $leave_start_date->diff($leave_end_date)->days + 1;

    // Calculate how many days have passed since the start date
    $days_passed = $leave_start_date->diff($current_date)->days;

    if ($current_date < $leave_start_date) {
        // If current date is before the leave starts, no progress has been made
        $days_passed = 0;
    } elseif ($current_date > $leave_end_date) {
        // If current date is after the leave ends, progress is complete
        $days_passed = $leave_duration;
    }

    // Calculate the percentage of progress
    $progress_percentage = ($days_passed / $leave_duration) * 100;
} else {
    // If no approved leave, set leave data to null
    $approved_leave_data = null;
}

// Close the database connections
$stmt_employee->close();
$stmt_leave->close();
$stmt_approved_leave->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Balance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="../../css/styles.css"> <!-- If you have custom styles -->
    <style>
        .progress-bar {
            width: 100%;
            background-color: #e0e0e0;
        }
        .progress-bar div {
            height: 20px;
            background-color: #4caf50;
            width: 0%;
        }
        .table-dark {
            --bs-table-bg: #2d3238;
            --bs-table-border-color: #495057;
        }
        .card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .badge {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body class="bg-black text-light">
    <div class="container mt-5 bg-dark text-light p-4 rounded">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-light mb-0">Leave Details</h2>
            <div>
                <a href="../../employee/supervisor/leave_file.php" class="btn btn-primary">Back</a>
                <?php if ($approved_leave_data): ?>
                    <button type="button" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#leaveScheduleModal">
                        View Ongoing Leave Schedule
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card bg-dark text-light border border-secondary mb-4">
            <div class="card-header border-bottom border-secondary">
                <h5 class="card-title mb-0">Employee Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo $employee_data['firstname'] . ' ' . $employee_data['lastname']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>ID No.:</strong> <?php echo $employee_data['e_id']; ?></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Position:</strong> <?php echo $employee_data['position']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Department:</strong> <?php echo $employee_data['department']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-dark text-light mt-4 mb-4 border border-secondary rounded">
            <div class="p-3 border-bottom border-secondary">
                <h5 class="mb-0">Leave Balance</h5>
            </div>
            <div class="p-3">
                <table class="table table-bordered table-dark">
                    <thead>
                        <tr>
                            <th>Leave Type</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($employee_gender == 'Female') { ?>
                            <tr>
                                <td>Bereavement Leave</td>
                                <td class="text-end">
                                    <span class="badge bg-<?php echo isset($leave_data['bereavement_leave']) && $leave_data['bereavement_leave'] > 0 ? 'primary' : 'secondary'; ?>">
                                        <?php echo isset($leave_data['bereavement_leave']) ? $leave_data['bereavement_leave'] : '0'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Emergency Leave</td>
                                <td class="text-end">
                                    <span class="badge <?php echo isset($leave_data['emergency_leave']) && $leave_data['emergency_leave'] > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <?php echo isset($leave_data['emergency_leave']) ? $leave_data['emergency_leave'] : '0'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Maternity Leave</td>
                                <td class="text-end">
                                    <span class="badge <?php echo isset($leave_data['maternity_leave']) && $leave_data['maternity_leave'] > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <?php echo isset($leave_data['maternity_leave']) ? $leave_data['maternity_leave'] : '0'; ?>
                                    </span>
                                </td>
                            </tr>
                            <!-- Additional female leave types -->
                        <?php } elseif ($employee_gender == 'Male') { ?>
                            <tr>
                                <td>Bereavement Leave</td>
                                <td class="text-end">
                                    <span class="badge bg-<?php echo isset($leave_data['bereavement_leave_male']) && $leave_data['bereavement_leave_male'] > 0 ? 'primary' : 'secondary'; ?>">
                                        <?php echo isset($leave_data['bereavement_leave_male']) ? $leave_data['bereavement_leave_male'] : '0'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Emergency Leave</td>
                                <td class="text-end">
                                    <span class="badge <?php echo isset($leave_data['emergency_leave_male']) && $leave_data['emergency_leave_male'] > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <?php echo isset($leave_data['emergency_leave_male']) ? $leave_data['emergency_leave_male'] : '0'; ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Parental Leave</td>
                                <td class="text-end">
                                    <span class="badge <?php echo isset($leave_data['parental_leave_male']) && $leave_data['parental_leave_male'] > 0 ? 'badge-primary' : 'badge-secondary'; ?>">
                                        <?php echo isset($leave_data['parental_leave_male']) ? $leave_data['parental_leave_male'] : '0'; ?>
                                    </span>
                                </td>
                            </tr>
                            <!-- Additional male leave types -->
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if (isset($approved_leave_data)): ?>
        <div class="card bg-dark text-light border border-secondary">
            <div class="card-header border-bottom border-secondary">
                <h5 class="card-title mb-0">Current Leave Status</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Start Date:</strong> <?php echo $approved_leave_data['start_date']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>End Date:</strong> <?php echo $approved_leave_data['end_date']; ?></p>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Leave Progress</span>
                        <span><?php echo $days_passed; ?> of <?php echo $leave_duration; ?> days (<?php echo round($progress_percentage); ?>%)</span>
                    </div>
                    <div class="progress-bar">
                        <div style="width: <?php echo $progress_percentage; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Modal -->
        <div class="modal fade" id="leaveScheduleModal" tabindex="-1" aria-labelledby="leaveScheduleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content bg-dark text-light">
                    <div class="modal-header border-bottom border-secondary">
                        <h5 class="modal-title" id="leaveScheduleModalLabel">Ongoing Leave Schedule</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <table class="table table-bordered border-secondary text-light">
                            <tbody>
                                <tr>
                                    <td><strong>Leave Start Date</strong></td>
                                    <td><?php echo $approved_leave_data['start_date']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Leave End Date</strong></td>
                                    <td><?php echo $approved_leave_data['end_date']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Leave Duration</strong></td>
                                    <td><?php echo $leave_duration; ?> days</td>
                                </tr>
                                <tr>
                                    <td><strong>Days Passed</strong></td>
                                    <td><?php echo $days_passed; ?> days</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="mt-3">
                            <p class="d-flex justify-content-between">
                                <span>Progress:</span>
                                <span><?php echo round($progress_percentage); ?>%</span>
                            </p>
                            <div class="progress bg-secondary">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress_percentage; ?>%" 
                                     aria-valuenow="<?php echo $progress_percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

