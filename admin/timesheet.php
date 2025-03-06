<?php
// Start the session
session_start();

// Include the database connection file
include '../db/db_conn.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['a_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get the selected month and year from the request (default to current month and year)
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('m');
$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Fetch all employees from the employee_register table
$employeeQuery = "SELECT e_id, CONCAT(firstname, ' ', lastname) AS name 
                  FROM employee_register";
$employeeResult = $conn->query($employeeQuery);
$employees = [];
while ($row = $employeeResult->fetch_assoc()) {
    $employees[$row['e_id']] = $row['name'];
}

// Fetch attendance logs for the selected month and year
$attendanceQuery = "SELECT e_id, attendance_date, time_in, time_out, status 
                    FROM attendance_log 
                    WHERE MONTH(attendance_date) = ? 
                    AND YEAR(attendance_date) = ?";
if ($attendanceStmt = $conn->prepare($attendanceQuery)) {
    $attendanceStmt->bind_param("ii", $selectedMonth, $selectedYear);
    $attendanceStmt->execute();
    $attendanceResult = $attendanceStmt->get_result();

    // Fetch all rows as an associative array
    $attendanceLogs = [];
    while ($row = $attendanceResult->fetch_assoc()) {
        $attendanceLogs[$row['attendance_date']][$row['e_id']] = $row;
    }

    // Close the statement
    $attendanceStmt->close();
} else {
    die("Error preparing attendance statement: " . $conn->error);
}

// Fetch holidays from non_working_days table
$holidays = [];
$holidayQuery = "SELECT date, description FROM non_working_days 
                 WHERE MONTH(date) = ? AND YEAR(date) = ?";
if ($holidayStmt = $conn->prepare($holidayQuery)) {
    $holidayStmt->bind_param("ii", $selectedMonth, $selectedYear);
    $holidayStmt->execute();
    $holidayResult = $holidayStmt->get_result();
    while ($holidayRow = $holidayResult->fetch_assoc()) {
        $holidays[$holidayRow['date']] = $holidayRow['description'];
    }
    $holidayStmt->close();
} else {
    die("Error preparing holiday statement: " . $conn->error);
}

// Fetch leave requests from leave_requests table
$leaveRequests = [];
$leaveQuery = "SELECT start_date, end_date, leave_type, e_id FROM leave_requests 
               WHERE status = 'Approved' 
               AND ((MONTH(start_date) = ? AND YEAR(start_date) = ?) 
               OR (MONTH(end_date) = ? AND YEAR(end_date) = ?))";

if ($leaveStmt = $conn->prepare($leaveQuery)) {
    $leaveStmt->bind_param("iiii", $selectedMonth, $selectedYear, $selectedMonth, $selectedYear);
    $leaveStmt->execute();
    $leaveResult = $leaveStmt->get_result();
    
    while ($leaveRow = $leaveResult->fetch_assoc()) {
        $startDate = new DateTime($leaveRow['start_date']);
        $endDate = new DateTime($leaveRow['end_date']);
        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

        foreach ($dateRange as $date) {
            $leaveRequests[$date->format('Y-m-d')][$leaveRow['e_id']] = $leaveRow['leave_type'];
        }
    }
    $leaveStmt->close();
} else {
    die("Error preparing leave statement: " . $conn->error);
}

// Close the database connection
$conn->close();

// Generate all dates for the selected month and year
$allDatesInMonth = [];
$numberOfDays = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$currentDate = new DateTime(); // Get current date/time

for ($day = 1; $day <= $numberOfDays; $day++) {
    $dateStr = sprintf('%04d-%02d-%02d', $selectedYear, $selectedMonth, $day);
    $dateObj = new DateTime($dateStr);
    $dayOfWeek = $dateObj->format('N'); // 1=Monday, 7=Sunday

    // Reset time components for accurate comparison
    $dateObj->setTime(0, 0, 0);
    $currentDate->setTime(0, 0, 0);

    // Determine status
    if ($dayOfWeek == 7) {
        $status = 'Day Off';
    } elseif (isset($holidays[$dateStr])) {
        $status = 'Holiday (' . $holidays[$dateStr] . ')';
    } else {
        $status = ($dateObj <= $currentDate) ? 'Absent' : 'No Record';
    }

    // Add the date to the array
    $allDatesInMonth[$dateStr] = [
        'date' => $dateStr,
        'status' => $status,
    ];
}

// Merge attendance logs with all dates for each employee
$employeeAttendance = [];
foreach ($allDatesInMonth as $dateStr => $dateInfo) {
    $employeeAttendance[$dateStr] = [];
    foreach ($employees as $e_id => $name) {
        if (isset($attendanceLogs[$dateStr][$e_id])) {
            // Use the attendance log if it exists
            $employeeAttendance[$dateStr][$e_id] = $attendanceLogs[$dateStr][$e_id];
        } elseif (isset($leaveRequests[$dateStr][$e_id])) {
            // Use leave request if it exists
            $employeeAttendance[$dateStr][$e_id] = [
                'e_id' => $e_id,
                'name' => $name,
                'attendance_date' => $dateStr,
                'time_in' => 'N/A',
                'time_out' => 'N/A',
                'total_hours' => 'N/A',
                'status' => 'Leave (' . $leaveRequests[$dateStr][$e_id] . ')',
            ];
        } else {
            // Mark as absent if no attendance or leave record exists
            $employeeAttendance[$dateStr][$e_id] = [
                'e_id' => $e_id,
                'name' => $name,
                'attendance_date' => $dateStr,
                'time_in' => 'N/A',
                'time_out' => 'N/A',
                'total_hours' => 'N/A',
                'status' => $dateInfo['status'],
            ];
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Timesheet</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="../css/styles.css" rel="stylesheet">
    <style>
        /* Custom Dark Theme Styles */
        body {
            background-color: #121212; /* Dark background */
            color: #ffffff; /* Light text */
        }

        .badge {
            font-size: 14px;
            padding: 5px 10px;
        }

        .month-selector {
            margin-bottom: 20px;
        }

        th, td {
            text-align: center;
        }

        /* Dark Table Styles */
        .table-dark {
            background-color: #343a40; /* Dark table header */
        }

        .table-bordered {
            border-color: white; /* Dark border color */
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: gray; /* Darker row for striped effect */
        }

        .table-striped tbody tr:nth-of-type(even) {
            background-color: #2d2d2d; /* Lighter row for striped effect */
        }

        .table-hover tbody tr:hover {
            background-color: #3e3e3e; /* Hover effect */
        }

        .form-control {
            background-color: #2d2d2d; /* Dark input background */
            color: #ffffff; /* Light text */
            border-color: #454d55; /* Dark border */
        }

        .form-control:focus {
            background-color: #3e3e3e; /* Darker input background on focus */
            color: #ffffff; /* Light text */
            border-color: #555; /* Darker border on focus */
        }

        .btn-primary {
            background-color: #0d6efd; /* Bootstrap primary color */
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            background-color: #0b5ed7; /* Darker primary color on hover */
            border-color: #0b5ed7;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2 class="mb-4">Admin Timesheet</h2>

    <!-- Month and Year Selector -->
    <form method="GET" class="month-selector">
        <div class="row justify-content-end"> <!-- Add justify-content-end to align the row to the right -->
            <div class="col-md-3">
                <label for="month">Select Month:</label>
                <select name="month" id="month" class="form-control">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php echo ($i == $selectedMonth) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $i, 10)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="year">Select Year:</label>
                <input type="number" name="year" id="year" class="form-control" value="<?php echo $selectedYear; ?>" min="2000" max="<?php echo date('Y'); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary mt-4" aria-label="Filter timesheet">Filter</button>
            </div>
        </div>
    </form>


    <table id="timesheet" class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Time-In</th>
                <th>Time-Out</th>
                <th>Total Hours</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($employeeAttendance)): ?>
                <tr>
                    <td colspan="6" class="text-center">No data available for the selected month and year.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($employeeAttendance as $dateStr => $logs): ?>
                    <?php foreach ($logs as $e_id => $log): ?>
                        <tr>
                            <td><?php echo date('F j, Y', strtotime($log['attendance_date'])); ?></td>
                            <td><?php echo $log['name']; ?></td>
                            <td><?php echo $log['time_in']; ?></td>
                            <td><?php echo $log['time_out']; ?></td>
                            <td><?php echo $log['total_hours']; ?></td>
                            <td>
                                <?php
                                // Set badge color based on status
                                $statusClass = '';
                                switch ($log['status']) {
                                    case 'Present':
                                        $statusClass = 'bg-success';
                                        break;
                                    case 'Late':
                                        $statusClass = 'bg-warning text-dark';
                                        break;
                                    case 'Overtime':
                                        $statusClass = 'bg-primary';
                                        break;
                                    case 'Holiday':
                                        $statusClass = 'bg-danger';
                                        break;
                                    case 'Leave':
                                        $statusClass = 'bg-danger';
                                        break;
                                    case 'Day Off':
                                        $statusClass = 'bg-secondary';
                                        break;
                                    case 'Absent':
                                        $statusClass = 'bg-danger';
                                        break;
                                    case 'No Record':
                                        $statusClass = 'bg-light text-dark';
                                        break;
                                    default:
                                        $statusClass = 'bg-light text-dark';
                                        break;
                                }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo $log['status']; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>