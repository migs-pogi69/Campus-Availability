<?php require __DIR__ . '/../assets/config.php'; requireLogin(); ?>
<?php /** @var array $counts**/ ?>
<?php /** @var array $types**/ ?>
<?php /** @var array $locations**/ ?>
<?php /** @var array $resources**/ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Availability</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../Styles/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
</head>
<body>
    <?php include __DIR__ . '/../assets/navbar.php'; ?>

    <main class="container">

        <section class="page-header">
            <div class="page-header-left">
                <div class="title-row">
                    <h1>Live Availability Board</h1>
                    <span class="live-badge"><span class="live-dot"></span>Live</span>
                </div>
                <p class="subtitle">Real-time status of classrooms, labs, projectors, and AV equipment</p>
            </div>

            <div class="page-header-right">
                <span class="synced">
                    Last synced: <strong id="last-synced"><?= date('h:i:s A')  ?></strong>
                </span>
                <button class="refresh-btn" id="refresh-btn" aria-label="Refresh">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 11a8 8 0 00-14.9-3M4 5v4h4"/>
                        <path d="M4 13a8 8 0 0014.9 3M20 19v-4h-4"/>
                    </svg>
                </button>
            </div>
        </section>
        
        <section class="stats">

            <div class="stat-card">
                <div class="stat-icon green">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 10h18M9 15l2 2 4-4"/></svg>
                </div>
                <div>
                    <div class="stat-number green" id="count-available"><?= $counts['available'] ?></div>
                    <div class="stat-label">Available</div>
                    <div class="stat-sub">Resources ready</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </div>
                <div>
                    <div class="stat-number orange" id="count-booked"><?= $counts['booked'] ?></div>
                    <div class="stat-label">Booked</div>
                    <div class="stat-sub">Currently reserved</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <svg viewBox="0 0 24 24"><path d="M14.7 6.3a4 4 0 005.4 5.4l-9.4 9.4a2.1 2.1 0 01-3-3l9.4-9.4a4 4 0 00-2.4-2.4z"/></svg>
                </div>
                <div>
                    <div class="stat-number red" id="count-repair"><?= $counts['repair'] ?></div>
                    <div class="stat-label">Under Repair</div>
                    <div class="stat-sub">Needs attention</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                     <svg viewBox="0 0 24 24"><path d="M12 2l9 5v10l-9 5-9-5V7z"/><path d="M12 22V12M3 7l9 5 9-5"/></svg>
                </div>
                <div>
                    <div class="stat-number purple" id="count-total"><?= $counts['total'] ?></div>
                    <div class="stat-label">Total Resources</div>
                    <div class="stat-sub">Across campus</div>
                </div>
            </div>
        </section>

        <section class="toolbar">

            <label class="search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="text" id="search" placeholder="Search resources by name or location"/>
            </label>

            <select id="filter-type" class="select">
                <option value="">All Types</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-location" class="select">
                <option value="">All Locations</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= htmlspecialchars($l) ?>"><?= htmlspecialchars($l) ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-status" class="select">
                <option value="">All Statuses</option>
                <option value="available">Available</option>
                <option value="booked">Booked</option>
                <option value="repair">Under Repair</option>
            </select>

            <div class="view-toggle">
                <button class="view-btn active" id="view-grid" aria-label="Grid view">
                    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                </button>
                <button class="view-btn" id="view-list" aria-label="List view">
                    <svg viewBox="0 0 24 24"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
                </button>
            </div>

            <?php if (isAdmin()): ?>
                <a class="add-btn" id="add-resource" href="resources.php">
                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Add Resource
                </a>
            <?php endif; ?>
        </section>

        <section class="resource-grid" id="resource-grid">
    <?php foreach ($resources as $r): ?>
      <?php include __DIR__ . '/../assets/resource-card.php'; ?>
    <?php endforeach; ?>
  </section>

  <p class="empty-msg" id="empty-msg" hidden>No resources match your filters.</p>
     
    </main>

    <script src="../javascript/script.js"></script>
</body>
</html>