<?php
session_start();
include("../config/db.php");

// 1. Secure Access Check: Ensure user is logged in (GN or Admin Roles)
if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], [2, 5]) || empty($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$complaint_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($complaint_id === 0) {
    die("Invalid Complaint ID.");
}

$message = "";

// --- AJAX ENDPOINT: FILTER BY ROLE TYPE AND SEARCH AREA NAME ---
if (isset($_GET['action']) && $_GET['action'] === 'search_area' && isset($_GET['term']) && isset($_GET['role_level'])) {
    header('Content-Type: application/json');
    
    $term = '%' . trim($_GET['term']) . '%';
    $role_level = intval($_GET['role_level']); // 2, 3, or 4
    
    // Check both gn_division and ds_division dynamically
    $search_q = "SELECT user_id, f_name, l_name, role_id, gn_division, ds_division 
                 FROM users 
                 WHERE role_id = ? 
                 AND (gn_division LIKE ? OR ds_division LIKE ?) 
                 LIMIT 10";
                 
    $stmt = mysqli_prepare($conn, $search_q);
    mysqli_stmt_bind_param($stmt, "iss", $role_level, $term, $term);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $output = [];
    while($row = mysqli_fetch_assoc($result)) {
        $role_label = 'Official';
        if ($row['role_id'] == 2) $role_label = 'Grama Niladhari';
        if ($row['role_id'] == 3) $role_label = 'Local Authority';
        if ($row['role_id'] == 4) $role_label = 'Divisional Secretariat';

        $output[] = [
            'user_id' => $row['user_id'],
            'name' => $row['f_name'] . ' ' . $row['l_name'],
            'role_title' => $role_label,
            'area_name' => !empty($row['gn_division']) ? $row['gn_division'] : $row['ds_division']
        ];
    }
    echo json_encode($output);
    exit();
}

// 2. Handle Action Submissions (Form Submissions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_type'])) {
        
        if ($_POST['action_type'] === 'update_complaint') {
            $new_status_id = intval($_POST['status_id']);
            $reply_text = trim($_POST['reply_text']);
            
            $update_q = "UPDATE complaints SET status_id = ?, reply = ?, updated_at = NOW() WHERE complaint_id = ? AND assigned_to_id = ?";
            $stmt = mysqli_prepare($conn, $update_q);
            mysqli_stmt_bind_param($stmt, "isii", $new_status_id, $reply_text, $complaint_id, $officer_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "<div class='alert success'>Complaint updated successfully.</div>";
            } else {
                $message = "<div class='alert error'>Error updating record database configurations.</div>";
            }
            mysqli_stmt_close($stmt);
        }

        if ($_POST['action_type'] === 'escalate_complaint') {
            $target_officer_id = intval($_POST['target_officer_id']);
            
            if ($target_officer_id > 0) {
                $escalate_q = "UPDATE complaints SET assigned_to_id = ?, status_id = 2, updated_at = NOW() WHERE complaint_id = ?";
                $stmt = mysqli_prepare($conn, $escalate_q);
                mysqli_stmt_bind_param($stmt, "ii", $target_officer_id, $complaint_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "<div class='alert success'>Complaint successfully escalated.</div>";
                } else {
                    $message = "<div class='alert error'>Failed to process escalation assignment.</div>";
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "<div class='alert error'>Please select an official from the search dropdown.</div>";
            }
        }
    }
}

// 3. Fetch current complaint information
$query = "SELECT c.*, cc.category_name, cs.status_name 
          FROM complaints c
          LEFT JOIN complaint_categories cc ON c.category_id = cc.category_id
          LEFT JOIN complaint_status cs ON c.status_id = cs.status_id
          WHERE c.complaint_id = ? AND c.assigned_to_id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "ii", $complaint_id, $officer_id);
mysqli_stmt_execute($stmt);
$complaint = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$complaint) {
    die("Complaint records not found.");
}

// 4. AUTOMATIC PROXIMITY SEARCH: Nearest official breakdown
$comp_lat = floatval($complaint['latitude']);
$comp_lng = floatval($complaint['longitude']);

$proximity_q = "SELECT user_id, f_name, l_name, role_id, gn_division, ds_division,
                (6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(office_latitude)) * COS(RADIANS(office_longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(office_latitude)))) AS distance 
                FROM users 
                WHERE role_id IN (2, 3, 4) 
                AND user_id != ? 
                AND office_latitude IS NOT NULL 
                AND office_longitude IS NOT NULL
                ORDER BY distance ASC LIMIT 1";

$p_stmt = mysqli_prepare($conn, $proximity_q);
mysqli_stmt_bind_param($p_stmt, "dddi", $comp_lat, $comp_lng, $comp_lat, $officer_id);
mysqli_stmt_execute($p_stmt);
$closest_auth = mysqli_fetch_assoc(mysqli_stmt_get_result($p_stmt));
mysqli_stmt_close($p_stmt);

$closest_role_label = 'Official';
if ($closest_auth) {
    if ($closest_auth['role_id'] == 2) $closest_role_label = 'Grama Niladhari';
    if ($closest_auth['role_id'] == 3) $closest_role_label = 'Local Authority';
    if ($closest_auth['role_id'] == 4) $closest_role_label = 'Divisional Secretariat';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Investigate Complaint #<?php echo $complaint['complaint_id']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f2f5f2; margin: 0; padding-bottom: 60px; color: #333; }
        .header { background-color: #e65100; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .back-btn { background: #b33600; color: white; text-decoration: none; padding: 8px 15px; border-radius: 5px; font-weight: bold; font-size: 14px; }
        .wrapper { width: 85%; margin: 30px auto; display: flex; gap: 30px; }
        .left-panel { width: 60%; }
        .right-panel { width: 40%; display: flex; flex-direction: column; gap: 20px; }
        .content-box { background: white; padding: 25px; border-radius: 10px; box-shadow: 0px 2px 8px gray; margin-bottom: 25px; }
        h2, h3 { color: #e65100; margin-top: 0; border-bottom: 2px solid #ffccbc; padding-bottom: 8px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .meta-item b { color: #555; }
        .evidence-img { max-width: 100%; border-radius: 8px; border: 1px solid #ddd; margin-top: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .alert { padding: 12px; margin-bottom: 15px; border-radius: 5px; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #555; margin-top: 15px; }
        select, textarea, input[type="text"] { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 5px; font-size: 14px; }
        button { background-color: #e65100; color: white; border: none; padding: 12px 20px; cursor: pointer; border-radius: 5px; width: 100%; font-weight: bold; font-size: 14px; margin-top: 15px; text-transform: uppercase; }
        button:hover { background-color: #b33600; }
        
        /* Layout Fixes for Autocomplete Overlays */
        .proximity-suggestion { background-color: #fff3e0; border-left: 4px solid #ff9800; padding: 12px; margin: 10px 0; border-radius: 4px; }
        .radio-group { display: flex; gap: 15px; margin: 10px 0; background: #fafafa; padding: 10px; border-radius: 5px; border: 1px dashed #ccc; }
        .radio-option { display: flex; align-items: center; gap: 5px; font-size: 13px; font-weight: bold; cursor: pointer; color: #e65100; }
        .search-container { position: relative; margin-top: 15px; }
        
        /* CRITICAL POSITIONING TO SHOW DROPDOWN ON TOP */
        .autocomplete-suggestions { border: 1px solid #b33600; background: #ffffff !important; max-height: 200px; overflow-y: auto; position: absolute; width: 100%; z-index: 99999 !important; box-shadow: 0 5px 15px rgba(0,0,0,0.3); border-radius: 4px; left: 0; display: block; }
        .suggestion-item { padding: 12px; cursor: pointer; border-bottom: 1px solid #eee; color: #333 !important; text-align: left; }
        .suggestion-item:hover { background-color: #ffe0b2 !important; color: #b33600 !important; }
    </style>
</head>
<body>

<div class="header">
    <h1>Haritha Investigation Desk</h1>
    <a href="../gn_dash.php" class="back-btn">← Back to Dashboard</a>
</div>

<div class="wrapper">
    <!-- Left Panel Info -->
    <div class="left-panel">
        <?php echo $message; ?>
        <div class="content-box">
            <h2>Complaint #<?php echo $complaint['complaint_id']; ?>: <?php echo htmlspecialchars($complaint['title']); ?></h2>
            <div class="meta-grid">
                <div class="meta-item"><b>Category:</b> <?php echo htmlspecialchars($complaint['category_name'] ?? 'Unassigned'); ?></div>
                <div class="meta-item"><b>Current Status:</b> <?php echo htmlspecialchars($complaint['status_name']); ?></div>
                <div class="meta-item"><b>Reported Date:</b> <?php echo $complaint['created_at']; ?></div>
                <div class="meta-item"><b>Coordinates:</b> <?php echo $complaint['latitude'] . ", " . $complaint['longitude']; ?></div>
            </div>
            <label>Incident Details</label>
            <p style="background: #fafafa; padding: 15px; border-radius: 5px; border-left: 4px solid #b0bec5;"><?php echo nl2br(htmlspecialchars($complaint['description'])); ?></p>
        </div>
    </div>

    <!-- Right Escalation Form Side -->
    <div class="right-panel">
        <div class="content-box">
            <h3>Manage Progress</h3>
            <form method="POST" action="">
                <input type="hidden" name="action_type" value="update_complaint">
                <label for="status_id">Update Status Matrix</label>
                <select name="status_id" id="status_id">
                    <option value="1" <?php if($complaint['status_id'] == 1) echo 'selected'; ?>>SUBMITTED</option>
                    <option value="2" <?php if($complaint['status_id'] == 2) echo 'selected'; ?>>ASSIGNED</option>
                    <option value="3" <?php if($complaint['status_id'] == 3) echo 'selected'; ?>>IN PROGRESS</option>
                    <option value="4" <?php if($complaint['status_id'] == 4) echo 'selected'; ?>>COMPLETED</option>
                </select>
                <label for="reply_text">Add Reply</label>
                <textarea name="reply_text" id="reply_text" rows="3"><?php echo htmlspecialchars($complaint['reply'] ?? ''); ?></textarea>
                <button type="submit">Save Assessment</button>
            </form>
        </div>

        <div class="content-box">
            <h3>Escalate Jurisdiction</h3>
            <form method="POST" action="">
                <input type="hidden" name="action_type" value="escalate_complaint">
                <input type="hidden" id="target_officer_id" name="target_officer_id" value="">

                <label>Select Target Authority Level</label>
                <div class="radio-group">
                    <label class="radio-option"><input type="radio" name="authority_level" value="2" onclick="handleLevelSelection()"> GN</label>
                    <label class="radio-option"><input type="radio" name="authority_level" value="3" onclick="handleLevelSelection()"> Local Authority</label>
                    <label class="radio-option"><input type="radio" name="authority_level" value="4" onclick="handleLevelSelection()"> DS</label>
                </div>

                <div id="search_field_wrapper" style="display: none;" class="search-container">
                    <label for="area_search" id="search_label_text">Search Area Division</label>
                    <input type="text" id="area_search" placeholder="Type area name..." autocomplete="off">
                    <div id="suggestions_box" class="autocomplete-suggestions" style="display: none;"></div>
                </div>

                <label style="margin-top: 15px;">Selected Recipient Profile</label>
                <input type="text" id="selected_auth_display" value="" readonly style="background-color: #eee;">

                <button type="submit" style="background-color: #d84315;">Confirm Escalation</button>
            </form>
        </div>
    </div>
</div>

<script>
function handleLevelSelection() {
    document.getElementById('search_field_wrapper').style.display = 'block';
    document.getElementById('area_search').value = '';
    document.getElementById('target_officer_id').value = '';
    document.getElementById('selected_auth_display').value = '';
    document.getElementById('suggestions_box').style.display = 'none';
    
    let selectedLevel = document.querySelector('input[name="authority_level"]:checked').value;
    let textLabel = "Search Division";
    if(selectedLevel == 2) textLabel = "Search GN Division Name";
    if(selectedLevel == 3) textLabel = "Search Local Authority Area";
    if(selectedLevel == 4) textLabel = "Search DS Division Name";
    document.getElementById('search_label_text').innerText = textLabel;
}

document.getElementById('area_search').addEventListener('input', function() {
    let term = this.value.trim();
    let suggestionsBox = document.getElementById('suggestions_box');
    let selectedRadio = document.querySelector('input[name="authority_level"]:checked');
    
    if (!selectedRadio) return;
    let roleLevel = selectedRadio.value;
    
    if (term.length < 1) {
        suggestionsBox.style.display = 'none';
        return;
    }

    let url = `view_complaint.php?id=<?php echo $complaint_id; ?>&action=search_area&term=${encodeURIComponent(term)}&role_level=${roleLevel}`;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            console.log("AJAX Data Received:", data); // View your web console log to check results!
            suggestionsBox.innerHTML = '';
            
            if (data.length > 0) {
                data.forEach(item => {
                    let div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.innerHTML = `<b>${item.area_name}</b> - ${item.name} <small>(${item.role_title})</small>`;
                    
                    div.addEventListener('click', function() {
                        document.getElementById('target_officer_id').value = item.user_id;
                        document.getElementById('selected_auth_display').value = `${item.name} [${item.role_title}]`;
                        document.getElementById('area_search').value = item.area_name;
                        suggestionsBox.style.display = 'none';
                    });
                    suggestionsBox.appendChild(div);
                });
                suggestionsBox.style.display = 'block';
            } else {
                suggestionsBox.style.display = 'none';
            }
        })
        .catch(err => console.error("Fetch failure:", err));
});

document.addEventListener('click', function(e) {
    if (e.target.id !== 'area_search') {
        document.getElementById('suggestions_box').style.display = 'none';
    }
});
</script>
</body>
</html>