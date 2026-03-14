<?php
require_once '../config.php';
requireLogin();

$db    = getDB();
$admin = currentAdmin();
$tab   = $_GET['tab'] ?? 'facilitators';
$msg   = '';
$error = '';

// ── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    // Add facilitator
    if ($postAction === 'add_facilitator') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $error = 'Facilitator name is required.';
        } else {
            $check = $db->prepare("SELECT id FROM facilitators WHERE name = ?");
            $check->execute([$name]);
            if ($check->fetch()) {
                $error = 'Facilitator already exists.';
            } else {
                $db->prepare("INSERT INTO facilitators (name) VALUES (?)")->execute([$name]);
                auditLog('ADD_FACILITATOR', 'facilitator', 0, $name);
                $msg = 'Facilitator added!';
            }
        }
        $tab = 'facilitators';
    }

    // Delete facilitator
    if ($postAction === 'delete_facilitator') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM facilitators WHERE id=?")->execute([$id]);
        auditLog('DELETE_FACILITATOR', 'facilitator', $id);
        $msg = 'Facilitator deleted.';
        $tab = 'facilitators';
    }

    // Add location
    if ($postAction === 'add_location') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $error = 'Location name is required.';
        } else {
            $check = $db->prepare("SELECT id FROM locations WHERE name = ?");
            $check->execute([$name]);
            if ($check->fetch()) {
                $error = 'Location already exists.';
            } else {
                $db->prepare("INSERT INTO locations (name) VALUES (?)")->execute([$name]);
                auditLog('ADD_LOCATION', 'location', 0, $name);
                $msg = 'Location added!';
            }
        }
        $tab = 'locations';
    }

    // Delete location
    if ($postAction === 'delete_location') {
        $id = (int)$_POST['id'];
        // Don't delete the default location
        $check = $db->prepare("SELECT is_default FROM locations WHERE id=?");
        $check->execute([$id]);
        $loc = $check->fetch();
        if ($loc && $loc['is_default']) {
            $error = 'Cannot delete the default location.';
        } else {
            $db->prepare("DELETE FROM locations WHERE id=?")->execute([$id]);
            auditLog('DELETE_LOCATION', 'location', $id);
            $msg = 'Location deleted.';
        }
        $tab = 'locations';
    }

    // Add vessel
    if ($postAction === 'add_vessel') {
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            $error = 'Vessel name is required.';
        } else {
            $check = $db->prepare("SELECT id FROM vessels WHERE name = ?");
            $check->execute([$name]);
            if ($check->fetch()) {
                $error = 'Vessel already exists.';
            } else {
                $db->prepare("INSERT INTO vessels (name) VALUES (?)")->execute([$name]);
                auditLog('ADD_VESSEL', 'vessel', 0, $name);
                $msg = 'Vessel added!';
            }
        }
        $tab = 'vessels';
    }

    // Delete vessel
    if ($postAction === 'delete_vessel') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM vessels WHERE id=?")->execute([$id]);
        auditLog('DELETE_VESSEL', 'vessel', $id);
        $msg = 'Vessel deleted.';
        $tab = 'vessels';
    }
}

// ── Fetch data ───────────────────────────────────────────────
$facilitators = $db->query("SELECT * FROM facilitators WHERE is_active=1 ORDER BY name ASC")->fetchAll();
$locations    = $db->query("SELECT * FROM locations WHERE is_active=1 ORDER BY is_default DESC, name ASC")->fetchAll();
$vessels      = $db->query("SELECT * FROM vessels WHERE is_active=1 ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Settings — 88 Aces</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
<style>
.settings-tabs{display:flex;gap:4px;background:#f3f4f6;border-radius:10px;padding:4px;margin-bottom:24px;width:fit-content;}
.tab-btn{padding:9px 20px;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;border:none;background:transparent;color:#6b7280;font-family:inherit;transition:all .2s;}
.tab-btn.active{background:#fff;color:#1a4a8a;font-weight:600;box-shadow:0 1px 4px rgba(0,0,0,.1);}
.add-form{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
.add-form input{flex:1;min-width:250px;padding:10px 14px;border:1.5px solid #e5e7eb;border-radius:8px;font-size:14px;font-family:inherit;outline:none;}
.add-form input:focus{border-color:#1a4a8a;box-shadow:0 0 0 3px rgba(26,74,138,.1);}
.add-form button{padding:10px 20px;background:#1a4a8a;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;font-family:inherit;}
.item-list{display:flex;flex-direction:column;gap:8px;}
.item-row{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb;}
.item-name{font-size:14px;font-weight:500;color:#1f2937;}
.default-badge{font-size:11px;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:20px;font-weight:600;margin-left:8px;}
.delete-btn{background:none;border:none;cursor:pointer;font-size:18px;color:#9ca3af;transition:color .2s;padding:4px;}
.delete-btn:hover{color:#dc2626;}
.empty-state{text-align:center;padding:40px;color:#9ca3af;font-size:14px;}
.hidden{display:none!important;}
.count-badge{background:#e8f0fb;color:#1a4a8a;border-radius:20px;padding:2px 8px;font-size:12px;font-weight:600;margin-left:6px;}
</style>
</head>
<body>
<?php include 'partials/sidebar.php'; ?>
<main class="main-content">
  <?php include 'partials/topbar.php'; ?>
  <div class="page-body">

    <div class="page-header">
      <h2>⚙️ Settings</h2>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-success">✅ <?= sanitize($msg) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger">⚠ <?= sanitize($error) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="settings-tabs">
      <button class="tab-btn <?= $tab === 'facilitators' ? 'active' : '' ?>" onclick="switchTab('facilitators')">
        👤 Facilitators <span class="count-badge"><?= count($facilitators) ?></span>
      </button>
      <button class="tab-btn <?= $tab === 'locations' ? 'active' : '' ?>" onclick="switchTab('locations')">
        📍 Locations <span class="count-badge"><?= count($locations) ?></span>
      </button>
      <button class="tab-btn <?= $tab === 'vessels' ? 'active' : '' ?>" onclick="switchTab('vessels')">
        🚢 Vessels <span class="count-badge"><?= count($vessels) ?></span>
      </button>
    </div>

    <!-- ── FACILITATORS TAB ── -->
    <div id="tab-facilitators" class="<?= $tab !== 'facilitators' ? 'hidden' : '' ?>">
      <div class="card">
        <div class="card-header">
          <h3>👤 Facilitators</h3>
          <span style="font-size:13px;color:#6b7280">These appear as dropdown options when creating a session</span>
        </div>
        <div class="card-body">
          <form method="POST" class="add-form">
            <input type="hidden" name="action" value="add_facilitator">
            <input type="text" name="name" placeholder="Enter facilitator name e.g. JUAN DELA CRUZ" required autocomplete="off">
            <button type="submit">+ Add Facilitator</button>
          </form>
          <div class="item-list">
            <?php if (empty($facilitators)): ?>
              <div class="empty-state">No facilitators added yet.</div>
            <?php else: ?>
              <?php foreach ($facilitators as $f): ?>
              <div class="item-row">
                <span class="item-name">👤 <?= sanitize($f['name']) ?></span>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this facilitator?')">
                  <input type="hidden" name="action" value="delete_facilitator">
                  <input type="hidden" name="id" value="<?= $f['id'] ?>">
                  <button type="submit" class="delete-btn" title="Delete">🗑</button>
                </form>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── LOCATIONS TAB ── -->
    <div id="tab-locations" class="<?= $tab !== 'locations' ? 'hidden' : '' ?>">
      <div class="card">
        <div class="card-header">
          <h3>📍 Locations</h3>
          <span style="font-size:13px;color:#6b7280">The default location is pre-selected when creating a session</span>
        </div>
        <div class="card-body">
          <form method="POST" class="add-form">
            <input type="hidden" name="action" value="add_location">
            <input type="text" name="name" placeholder="Enter location e.g. 5th Floor Ayala Building" required autocomplete="off">
            <button type="submit">+ Add Location</button>
          </form>
          <div class="item-list">
            <?php if (empty($locations)): ?>
              <div class="empty-state">No locations added yet.</div>
            <?php else: ?>
              <?php foreach ($locations as $l): ?>
              <div class="item-row">
                <span class="item-name">
                  📍 <?= sanitize($l['name']) ?>
                  <?php if ($l['is_default']): ?>
                    <span class="default-badge">DEFAULT</span>
                  <?php endif; ?>
                </span>
                <?php if (!$l['is_default']): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this location?')">
                  <input type="hidden" name="action" value="delete_location">
                  <input type="hidden" name="id" value="<?= $l['id'] ?>">
                  <button type="submit" class="delete-btn" title="Delete">🗑</button>
                </form>
                <?php else: ?>
                  <span style="font-size:12px;color:#9ca3af">Cannot delete default</span>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- ── VESSELS TAB ── -->
    <div id="tab-vessels" class="<?= $tab !== 'vessels' ? 'hidden' : '' ?>">
      <div class="card">
        <div class="card-header">
          <h3>🚢 Vessels</h3>
          <span style="font-size:13px;color:#6b7280">Seafarers will pick from this list when filling the attendance form</span>
        </div>
        <div class="card-body">
          <form method="POST" class="add-form">
            <input type="hidden" name="action" value="add_vessel">
            <input type="text" name="name" placeholder="Enter vessel name e.g. MV PRINCESS OF THE STARS" required autocomplete="off">
            <button type="submit">+ Add Vessel</button>
          </form>
          <div class="item-list">
            <?php if (empty($vessels)): ?>
              <div class="empty-state">No vessels added yet. Seafarers will type manually until vessels are added.</div>
            <?php else: ?>
              <?php foreach ($vessels as $v): ?>
              <div class="item-row">
                <span class="item-name">🚢 <?= sanitize($v['name']) ?></span>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this vessel?')">
                  <input type="hidden" name="action" value="delete_vessel">
                  <input type="hidden" name="id" value="<?= $v['id'] ?>">
                  <button type="submit" class="delete-btn" title="Delete">🗑</button>
                </form>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>
<script>
function switchTab(tab) {
  ['facilitators','locations','vessels'].forEach(t => {
    document.getElementById('tab-' + t).classList.toggle('hidden', t !== tab);
  });
  document.querySelectorAll('.tab-btn').forEach((btn, i) => {
    const tabs = ['facilitators','locations','vessels'];
    btn.classList.toggle('active', tabs[i] === tab);
  });
}
</script>
</body>
</html>
