<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1 || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];
$error = "";
$success = "";

// 2. Fetch complaint categories dynamically
$categories = [];
$cat_query = "SELECT category_id, category_name FROM complaint_categories ORDER BY category_id ASC";
$cat_result = mysqli_query($conn, $cat_query);
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row;
    }
}

// 3. Process Form Submission
if (isset($_POST['submit_complaint'])) {
    $title                = trim($_POST['title']);
    $category_id          = intval($_POST['category_id']);
    $description          = trim($_POST['description']);
    $location_description = trim($_POST['location_description']);
    
    $latitude  = (isset($_POST['latitude']) && $_POST['latitude'] !== '') ? floatval($_POST['latitude']) : null;
    $longitude = (isset($_POST['longitude']) && $_POST['longitude'] !== '') ? floatval($_POST['longitude']) : null;
    
    $status_id = 1; // Pending
    $image_path = null;

    if (empty($title) || empty($description) || empty($category_id)) {
        $error = "Title, Category, and Description are required fields.";
    } else {
        
        // Handle Optional Image Upload
        if (isset($_FILES['complaint_image']) && $_FILES['complaint_image']['error'] == 0) {
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_name = $_FILES['complaint_image']['name'];
            $file_tmp  = $_FILES['complaint_image']['tmp_name'];
            $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($file_ext, $allowed_extensions)) {
                $new_file_name = "IMG_" . uniqid() . "." . $file_ext;
                $upload_dir = "../uploads/";
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    $image_path = "uploads/" . $new_file_name;
                } else {
                    $error = "Failed to upload the image.";
                }
            } else {
                $error = "Invalid file extension. Only JPG, JPEG, and PNG are allowed.";
            }
        }

        // 4. Save Record and Auto-Escalate to Nearest GN via Haversine Formula
        if (empty($error)) {
            $sql = "INSERT INTO complaints (
                        citizen_id, category_id, status_id, title, 
                        description, image_path, latitude, longitude, 
                        location_description
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param(
                    $stmt, 
                    "iiissssss", 
                    $citizen_id, $category_id, $status_id, $title, 
                    $description, $image_path, $latitude, $longitude, 
                    $location_description
                );

                if (mysqli_stmt_execute($stmt)) {
                    $complaint_id = mysqli_insert_id($conn);
                    $success = "Your complaint has been submitted successfully!";
                    
                    // Proximity auto-routing calculations
                    if ($latitude !== null && $longitude !== null) {
                        $gn_sql = "SELECT user_id, 
                                          (6371 * ACOS(
                                              COS(RADIANS(?)) * COS(RADIANS(office_latitude)) * COS(RADIANS(office_longitude) - RADIANS(?)) + 
                                              SIN(RADIANS(?)) * SIN(RADIANS(office_latitude))
                                          )) AS distance 
                                   FROM users 
                                   WHERE role_id = 2 AND office_latitude IS NOT NULL 
                                   ORDER BY distance ASC 
                                   LIMIT 1";
                        
                        $gn_stmt = mysqli_prepare($conn, $gn_sql);
                        if ($gn_stmt) {
                            mysqli_stmt_bind_param($gn_stmt, "ddd", $latitude, $longitude, $latitude);
                            mysqli_stmt_execute($gn_stmt);
                            $gn_result = mysqli_stmt_get_result($gn_stmt);
                            
                            if ($closest_gn = mysqli_fetch_assoc($gn_result)) {
                                $assigned_gn_id = $closest_gn['user_id'];
                                
                                $update_sql = "UPDATE complaints SET assigned_to_id = ? WHERE complaint_id = ?";
                                $update_stmt = mysqli_prepare($conn, $update_sql);
                                if ($update_stmt) {
                                    mysqli_stmt_bind_param($update_stmt, "ii", $assigned_gn_id, $complaint_id);
                                    mysqli_stmt_execute($update_stmt);
                                    mysqli_stmt_close($update_stmt);
                                    $success = "Your complaint has been submitted and auto-escalated to the nearest Grama Niladhari officer!";
                                }
                            }
                            mysqli_stmt_close($gn_stmt);
                        }
                    }
                } else {
                    $error = "Database Error: Could not save your complaint. " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $error = "Database Error: Statement compilation failed.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Submit Environmental Complaint</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>

<div class="form-container">
    <h2>Report Environmental Issue</h2>
    <div id="map"></div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Complaint Title</label>
            <input type="text" id="title" name="title" placeholder="Brief title of the hazard" required>
        </div>

        <div class="form-group">
            <label for="category_id">Issue Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">-- Select a Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['category_id']; ?>">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Detailed Description</label>
            <textarea id="description" name="description" placeholder="Describe the environmental issue and its impact..." required></textarea>
        </div>

        <div class="form-group">
            <label for="complaint_image">Upload Photo Evidence (Optional)</label>
            <input type="file" id="complaint_image" name="complaint_image" accept="image/*">
        </div>

        <div class="form-group">
            <label for="location_description">Location Landmarks / Address</label>
            <input type="text" id="location_description" name="location_description" placeholder="e.g., Near the 4th milestone, opposite the school">
        </div>

        <div class="geo-group">
            <div class="form-group">
                <label for="latitude">Latitude (Optional)</label>
                <input type="text" id="latitude" name="latitude" placeholder="e.g., 6.9271" readonly>
            </div>
            <div class="form-group">
                <label for="longitude">Longitude (Optional)</label>
                <input type="text" id="longitude" name="longitude" placeholder="e.g., 79.8612" readonly>
            </div>
        </div>

        <button type="submit" name="submit_complaint">Submit Report</button>
    </form>

    <a href="citizen_dash.php" class="back-link">← Return to Dashboard</a>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>

<script src="../js/map_handler.js"></script>
</body>
</html>