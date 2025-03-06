<?php
// Set the default timezone
date_default_timezone_set('Asia/Manila');

// Include the database connection
include '../db/db_conn.php';

// Prepare the SQL query using prepared statements to prevent SQL injection
$sql = "SELECT al.timestamp, al.admin_name, al.action_type, al.affected_feature, al.details, al.ip_address
        FROM activity_logs al
        JOIN admin_register a ON al.admin_id = a.a_id
        WHERE al.affected_feature IN (?, ?, ?, ?, ?, ?)
        ORDER BY al.timestamp DESC";

// Initialize the statement
$stmt = $conn->prepare($sql);

// Bind parameters (hardcoded in this case, but they can be dynamic if needed)
$affectedFeature1 = 'Employee Details';
$affectedFeature2 = 'Employee Management';
$affectedFeature3 = 'Leave Information';
$affectedFeature4 = 'Admin Details';
$affectedFeature5 = 'Admin Management';
$affectedFeature6 = 'Evaluation';
$stmt->bind_param("ssssss", $affectedFeature1, $affectedFeature2, $affectedFeature3, $affectedFeature4, $affectedFeature5, $affectedFeature6);

// Execute the statement
if (!$stmt->execute()) {
    die("Error executing query: " . $conn->error);
}

// Fetch the result set
$result = $stmt->get_result();

// Close the statement early since it's no longer needed
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Activity Log</title>
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet">
    <link href="../css/styles.css" rel="stylesheet">
</head>
<body class="bg-black">
<div class="container mt-5 text-light">
    <h2>Admin Activity Log</h2>
    <div class="card mb-4 bg-dark text-light">
        <div class="card-header border-bottom border-1 border-warning d-flex justify-content-between align-items-center">
            <span>
                <i class="fas fa-table me-1"></i>
                Logs
            </span>
            <a class="btn btn-primary text-light" href="">Export</a>
        </div>
        <div class="card-body">
            <table id="datatablesSimple" class="table text-light text-center">
                <thead class="thead-light">
                    <tr class="text-center text-light">
                        <th>Timestamp</th>
                        <th>Action Taken By</th>
                        <th>Action</th>
                        <th>Affected Feature</th>
                        <th>Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Check if there are records and display them
                    if ($result->num_rows > 0) {
                        // Loop through the results and display each row in the table
                        while($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['timestamp']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['admin_name']) . "</td>";
                            
                            // Apply conditional classes to action_type
                            echo "<td>";
                            if ($row['action_type'] == 'changes') {
                                echo "<span class='text-warning'>Changes</span>";
                            } elseif ($row['action_type'] == 'created') {
                                echo "<span class='text-success'>Created</span>";
                            } elseif ($row['action_type'] == 'deleted') {
                                echo "<span class='text-danger'>Deleted</span>";
                            } else {
                                echo htmlspecialchars($row['action_type']);
                            }
                            echo "</td>";
                            
                            echo "<td>" . htmlspecialchars($row['affected_feature']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['details']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['ip_address']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        // If no records found
                        echo "<tr><td colspan='6' class='text-center'>No activity found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Include required JS libraries -->
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
<script src="../js/datatables-simple-demo.js"></script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>

<?php
// Close the database connection
$conn->close();
?>
