<?php
// Enable HTTP Gzip compression for 10x faster download speed
if (function_exists('ob_gzhandler') && !ob_start("ob_gzhandler")) {
    ob_start();
}

$initialDataJson = '{}';

// 1. Load live board data from Database or DataPersistence
try {
    require_once __DIR__ . '/api/controllers/BoardController.php';
    $boardCtrl = new BoardController();
    $boardRes = $boardCtrl->getFull(1);
    if (!empty($boardRes['success']) && !empty($boardRes['groups'])) {
        $initialDataJson = json_encode([
            'board' => $boardRes['board'],
            'columns' => array_merge($boardRes['main_columns'], $boardRes['sub_columns']),
            'groups' => $boardRes['groups']
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    // Fallback
}

// Fallback to static JSON if needed
if ($initialDataJson === '{}' || empty($initialDataJson)) {
    $jsonPath = __DIR__ . '/data/board_data.json';
    if (file_exists($jsonPath)) {
        $initialDataJson = file_get_contents($jsonPath);
    }
}

// Preload Auth Config and Session directly in PHP (Zero-Lag, 100% Reliable)
$authConfigFile = __DIR__ . '/api/config/auth_config.php';
$authConfig = file_exists($authConfigFile) ? include $authConfigFile : [];
if (!is_array($authConfig)) {
    $authConfig = [
        'google_client_id' => '834120129002-ov166c1k38dk91e1fe1e10jgjv689nb3.apps.googleusercontent.com',
        'allowed_domain' => '',
        'default_role' => 'member',
        'mock_mode_enabled' => true
    ];
}
if (empty($authConfig['google_client_id'])) {
    $authConfig['google_client_id'] = '834120129002-ov166c1k38dk91e1fe1e10jgjv689nb3.apps.googleusercontent.com';
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('isOwnerEmailCheck')) {
    function isOwnerEmailCheck($email) {
        $em = strtolower(trim((string)$email));
        return (
            strpos($em, 'kraijate') !== false ||
            strpos($em, 'krajjate') !== false ||
            strpos($em, 'jate') !== false ||
            strpos($em, 'admin@nigiwai') !== false
        );
    }
}

// Automatically enforce Admin role in DB for owner
try {
    require_once __DIR__ . '/api/config/database.php';
    $db = Database::getConnection();
    if ($db) {
        $db->exec("UPDATE users SET role = 'admin', name = 'Kraijate Sompong' WHERE email LIKE '%jate%' OR email = 'admin@nigiwaigroup.com'");
    }
} catch (Throwable $e) {}

if (isset($_SESSION['user'])) {
    if (isOwnerEmailCheck($_SESSION['user']['email'] ?? '')) {
        $_SESSION['user']['role'] = 'admin';
        $_SESSION['user']['name'] = 'Kraijate Sompong';
    }
}
$sessionUser = $_SESSION['user'] ?? null;
if ($sessionUser) {
    if (isOwnerEmailCheck($sessionUser['email'] ?? '')) {
        $sessionUser['role'] = 'admin';
        $sessionUser['name'] = 'Kraijate Sompong';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nigiwai PM - Monday-Style Project Management</title>
  
  <!-- Preloaded Data for Instant Zero-Lag Rendering -->
  <script>
    window.INITIAL_BOARD_DATA = <?= !empty($initialDataJson) ? $initialDataJson : '{}' ?>;
    window.PRELOADED_AUTH = {
      config: <?= json_encode($authConfig, JSON_UNESCAPED_UNICODE) ?>,
      user: <?= json_encode($sessionUser, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>

  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Google Identity Services (Google Sign-In) -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Noto Sans Thai"', 'sans-serif'],
          },
          colors: {
            monday: {
              blue: '#0073ea',
              'blue-hover': '#0060b9',
              green: '#00c875',
              orange: '#fdab3d',
              red: '#e2445c',
              purple: '#a25ddc',
              border: '#e6e9ef',
              hover: '#f5f6f8',
              surface: '#ffffff',
              bg: '#f7f9fb',
              rail: '#2b2c3a'
            }
          }
        }
      }
    }
  </script>

  <!-- Lucide Icons -->
  <script src="https://unpkg.com/lucide@latest"></script>
  <!-- Alpine.js Core -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <!-- Custom Monday Styling -->
  <link rel="stylesheet" href="assets/css/custom.css?v=<?= time() ?>">
</head>
<body class="bg-[#f7f9fb] text-[#323338] antialiased h-screen flex overflow-hidden select-none" x-data="mondayBoardApp" x-cloak>

  <!-- 1. LEFTMOST GLOBAL MINI RAIL (48px) -->
  <aside class="w-12 bg-[#2b2c3a] flex flex-col items-center justify-between py-3 text-gray-400 shrink-0 z-40">
    <div class="flex flex-col items-center gap-4 w-full">
      <!-- App Logo -->
      <a href="index.php" class="w-7 h-7 rounded-lg bg-gradient-to-tr from-blue-500 to-indigo-500 flex items-center justify-center text-white font-black text-sm shadow-md hover:scale-105 transition-transform" title="Nigiwai PM">
        N
      </a>

      <div class="w-6 h-[1px] bg-gray-700/60 my-1"></div>

      <!-- Nav Icons -->
      <button class="w-8 h-8 rounded-lg flex items-center justify-center text-white bg-white/15 hover:bg-white/20 transition-colors" title="Workspace">
        <i data-lucide="layout-grid" class="w-4 h-4"></i>
      </button>

      <button class="w-8 h-8 rounded-lg flex items-center justify-center hover:text-white hover:bg-white/10 transition-colors" title="Sidekick">
        <i data-lucide="sparkles" class="w-4 h-4"></i>
      </button>

      <button class="w-8 h-8 rounded-lg flex items-center justify-center hover:text-white hover:bg-white/10 transition-colors" title="Agents">
        <i data-lucide="bot" class="w-4 h-4"></i>
      </button>

      <button class="w-8 h-8 rounded-lg flex items-center justify-center hover:text-white hover:bg-white/10 transition-colors" title="Workflows">
        <i data-lucide="git-branch" class="w-4 h-4"></i>
      </button>

      <button class="w-8 h-8 rounded-lg flex items-center justify-center hover:text-white hover:bg-white/10 transition-colors" title="Notetaker">
        <i data-lucide="file-text" class="w-4 h-4"></i>
      </button>

      <button class="w-8 h-8 rounded-lg flex items-center justify-center hover:text-white hover:bg-white/10 transition-colors" title="Favorites">
        <i data-lucide="star" class="w-4 h-4"></i>
      </button>
    </div>

    <!-- Bottom Profile / Settings -->
    <div class="flex flex-col items-center gap-3 w-full">
      <a href="setup.php" class="w-8 h-8 rounded-lg flex items-center justify-center hover:text-white hover:bg-white/10 transition-colors" title="ตั้งค่า Database">
        <i data-lucide="settings" class="w-4 h-4"></i>
      </a>
      <div class="w-7 h-7 rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-bold text-[11px] flex items-center justify-center shadow-sm" title="User Profile">
        OP
      </div>
    </div>
  </aside>

  <!-- 2. COLLAPSIBLE WORKSPACE SIDEBAR (260px) -->
  <aside 
    x-show="sidebarOpen"
    x-transition:enter="transition-all ease-out duration-200"
    x-transition:enter-start="w-0 opacity-0 -translate-x-full"
    x-transition:enter-end="w-64 opacity-100 translate-x-0"
    x-transition:leave="transition-all ease-in duration-200"
    x-transition:leave-start="w-64 opacity-100 translate-x-0"
    x-transition:leave-end="w-0 opacity-0 -translate-x-full"
    class="w-64 bg-[#f5f6f8] border-r border-[#e6e9ef] flex flex-col shrink-0 overflow-hidden z-30"
  >
    <!-- Sidebar Header -->
    <div class="p-3 border-b border-[#e6e9ef] flex items-center justify-between bg-white/70">
      <div class="flex items-center gap-1.5 font-bold text-xs text-gray-800 tracking-tight">
        <span>Workspace</span>
      </div>
      <div class="flex items-center gap-1">
        <button class="p-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-200/60" title="ค้นหาใน Workspace">
          <i data-lucide="search" class="w-3.5 h-3.5"></i>
        </button>
        <button class="p-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-200/60" title="ตัวเลือก">
          <i data-lucide="more-horizontal" class="w-3.5 h-3.5"></i>
        </button>
        <!-- Collapse Button (<<) -->
        <button 
          @click="toggleSidebar()" 
          class="p-1 rounded text-gray-400 hover:text-gray-700 hover:bg-gray-200/80 transition-colors ml-0.5" 
          title="ย่อ Sidebar (Collapse)"
        >
          <i data-lucide="chevrons-left" class="w-4 h-4"></i>
        </button>
      </div>
    </div>

    <!-- Workspace Selector Dropdown -->
    <div class="p-2.5 bg-white/50 border-b border-[#e6e9ef]">
      <div class="flex items-center gap-1.5">
        <div class="flex-1 flex items-center justify-between px-2.5 py-1.5 bg-white rounded-lg border border-gray-200 shadow-2xs hover:border-gray-300 cursor-pointer transition-colors">
          <div class="flex items-center gap-2 truncate">
            <span class="w-4 h-4 rounded bg-amber-500 text-white flex items-center justify-center text-[10px] font-bold">M</span>
            <span class="text-xs font-semibold text-gray-800 truncate" x-text="activeWorkspace"></span>
          </div>
          <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-gray-400 shrink-0"></i>
        </div>
        <button class="p-1.5 rounded-lg bg-white border border-gray-200 text-gray-500 hover:text-blue-600 hover:border-blue-300 shadow-2xs" title="สร้าง Board หรือ Folder ใหม่">
          <i data-lucide="plus" class="w-3.5 h-3.5"></i>
        </button>
      </div>
    </div>

    <!-- Sidebar Search -->
    <div class="p-2">
      <div class="relative">
        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-2 text-gray-400"></i>
        <input 
          type="text" 
          x-model="sidebarSearch" 
          placeholder="ค้นหาบอร์ดหรือโฟลเดอร์..." 
          class="w-full pl-8 pr-2.5 py-1 text-[11px] bg-white border border-gray-200 rounded-md focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder-gray-400"
        />
      </div>
    </div>

    <!-- Navigation Tree (Folders & Boards) -->
    <div class="flex-1 overflow-y-auto p-2 space-y-1.5 text-xs text-gray-700">
      
      <div class="flex items-center gap-2 px-2 py-1.5 rounded-md hover:bg-gray-200/50 cursor-pointer text-gray-600">
        <i data-lucide="settings-2" class="w-3.5 h-3.5 text-gray-400"></i>
        <span class="text-[11px] font-medium">Manage workspace</span>
      </div>

      <!-- FOLDERS LIST -->
      <template x-for="folder in workspaceFolders" :key="folder.id">
        <div class="space-y-0.5">
          <!-- Folder Header -->
          <div 
            @click="toggleFolder(folder.id)"
            class="flex items-center justify-between px-2 py-1.5 rounded-md hover:bg-gray-200/60 cursor-pointer text-gray-700 font-semibold group/folder"
          >
            <div class="flex items-center gap-1.5 truncate">
              <i 
                data-lucide="chevron-right" 
                class="w-3 h-3 text-gray-400 transition-transform duration-150"
                :class="{'rotate-90 text-gray-600': expandedFolders[folder.id]}"
              ></i>
              <i data-lucide="folder" class="w-3.5 h-3.5 text-blue-500"></i>
              <span class="text-[11px] truncate" x-text="folder.name"></span>
            </div>
            <button class="opacity-0 group-hover/folder:opacity-100 text-gray-400 hover:text-gray-600 p-0.5">
              <i data-lucide="plus" class="w-3 h-3"></i>
            </button>
          </div>

          <!-- Folder Children (Boards) -->
          <div x-show="expandedFolders[folder.id]" class="pl-4 space-y-0.5" style="display: none;">
            <template x-for="b in folder.boards" :key="b.id">
              <div 
                x-show="!sidebarSearch || b.name.toLowerCase().includes(sidebarSearch.toLowerCase())"
                @click="loadBoard(b.id)"
                class="sidebar-board-item flex items-center gap-2 px-2.5 py-1.5 rounded-md cursor-pointer transition-colors text-[11px]"
                :class="b.active ? 'active bg-blue-100/70 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-200/50'"
              >
                <i data-lucide="table-2" class="w-3.5 h-3.5 shrink-0" :class="b.active ? 'text-blue-600' : 'text-gray-400'"></i>
                <span class="truncate" x-text="b.name"></span>
              </div>
            </template>
          </div>
        </div>
      </template>

    </div>

    <!-- Bottom Plan Badge -->
    <div class="p-3 border-t border-[#e6e9ef] bg-white/60 text-center">
      <div class="flex items-center justify-between text-[11px] text-gray-500 font-medium">
        <span>Nigiwai Enterprise</span>
        <span class="text-emerald-600 font-bold">Active</span>
      </div>
    </div>
  </aside>

  <!-- 3. EXPAND SIDEBAR FLOATING BUTTON (When Collapsed) -->
  <button 
    x-show="!sidebarOpen" 
    @click="toggleSidebar()"
    class="fixed left-12 top-4 z-40 bg-white hover:bg-blue-50 border border-gray-200 hover:border-blue-400 text-gray-500 hover:text-blue-600 p-1.5 rounded-r-lg shadow-md transition-all flex items-center gap-1 text-[11px] font-semibold"
    title="ขยาย Sidebar (Expand)"
    style="display: none;"
  >
    <i data-lucide="chevrons-right" class="w-4 h-4"></i>
    <span>Workspace</span>
  </button>

  <!-- 4. MAIN CONTENT AREA (Board View & Data Grid) -->
  <div class="flex-1 flex flex-col min-w-0 bg-[#f7f9fb] overflow-hidden" x-data="excelImporter">
    
    <!-- TOP APP HEADER -->
    <header class="bg-white border-b border-[#e6e9ef] px-6 py-2.5 shrink-0 z-20 flex items-center justify-between shadow-xs">
      <div class="flex items-center gap-3">
        <button 
          @click="toggleSidebar()" 
          class="p-1.5 rounded-lg text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition-colors"
          :title="sidebarOpen ? 'ย่อ Sidebar' : 'ขยาย Sidebar'"
        >
          <i :data-lucide="sidebarOpen ? 'panel-left-close' : 'panel-left-open'" class="w-4 h-4"></i>
        </button>

        <div class="flex items-center gap-2">
          <h1 class="text-lg font-black text-gray-900 tracking-tight flex items-center gap-1.5">
            <span x-text="board.name"></span>
            <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 cursor-pointer"></i>
          </h1>
          <span class="live-indicator" title="Live Synced"></span>
        </div>
      </div>

      <!-- Action Controls -->
      <div class="flex items-center gap-2.5">
        <!-- Search Filter -->
        <div class="relative w-56">
          <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-2 text-gray-400"></i>
          <input 
            type="text" 
            x-model="searchQuery" 
            placeholder="ค้นหาชื่องาน, สถานะ..." 
            class="w-full pl-8 pr-2.5 py-1 text-xs bg-[#f5f6f8] hover:bg-[#e6e9ef] focus:bg-white focus:ring-1 focus:ring-[#0073ea] border border-transparent focus:border-[#0073ea] rounded-md transition-all outline-none"
          />
          <button x-show="searchQuery" @click="searchQuery = ''" class="absolute right-2 top-1.5 text-gray-400 hover:text-gray-600">
            <i data-lucide="x" class="w-3 h-3"></i>
          </button>
        </div>

        <!-- Sync Indicator -->
        <div class="flex items-center gap-1 text-[11px] text-gray-500 bg-gray-50 px-2 py-1 rounded border border-gray-200">
          <i data-lucide="refresh-cw" class="w-3 h-3 text-gray-400" :class="{'animate-spin text-blue-500': isSyncing}"></i>
          <span x-text="isSyncing ? 'ซิงค์...' : (lastSyncTime ? (lastSyncTime.includes(' ') ? lastSyncTime.split(' ')[1] : lastSyncTime.substring(11,19)) : 'ปกติ')"></span>
        </div>

        <!-- Excel Import Button -->
        <!-- Save View Button -->
        <button 
          @click="saveView()" 
          class="flex items-center gap-1.5 px-3 py-1 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-md shadow-sm transition-all hover:scale-105"
          :class="{'opacity-75 cursor-not-allowed': isSavingView}"
          :disabled="isSavingView"
          title="บันทึกมุมมองและข้อมูลทั้งหมดของบอร์ดนี้ (Save View)"
        >
          <i data-lucide="save" class="w-3.5 h-3.5" x-show="!isSavingView"></i>
          <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin" x-show="isSavingView"></i>
          <span x-text="isSavingView ? 'กำลังบันทึก...' : 'Save View'"></span>
        </button>

        <!-- Import Excel Button -->
        <button 
          @click="openModal()" 
          class="flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 rounded-md shadow-2xs transition-colors"
        >
          <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i>
          <span>Import Excel</span>
        </button>

        <!-- Database Setup Button -->
        <a 
          href="setup.php" 
          class="flex items-center gap-1 px-2 py-1 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md border border-gray-200 shadow-2xs transition-colors"
          title="ตั้งค่าการเชื่อมต่อฐานข้อมูล"
        >
          <i data-lucide="settings-2" class="w-3.5 h-3.5 text-gray-500"></i>
          <span>ตั้งค่า DB</span>
        </a>

        <div class="h-5 w-[1px] bg-gray-300 mx-0.5"></div>

        <!-- USER PROFILE & AUTHENTICATION SECTION -->
        <div class="relative" @click.away="userDropdownOpen = false">
          
          <!-- State 1: NOT Logged In -->
          <template x-if="!isLoggedIn()">
            <button 
              @click="showLoginModal = true" 
              class="flex items-center gap-1.5 px-3 py-1 text-xs font-bold text-gray-700 bg-white hover:bg-gray-50 active:bg-gray-100 rounded-md border border-gray-300 shadow-2xs transition-all hover:scale-105"
              title="เข้าสู่ระบบด้วย Google หรือเลือก Role จำลอง"
            >
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
              </svg>
              <span>เข้าสู่ระบบ</span>
            </button>
          </template>

          <!-- State 2: Logged In -->
          <template x-if="isLoggedIn()">
            <div class="flex items-center gap-2">
              <!-- Quick Admin Console Button for Admins -->
              <button 
                x-show="isAdmin()" 
                @click="openUserManagementModal()" 
                class="hidden sm:flex items-center gap-1.5 px-3 py-1 rounded-full bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white text-[11px] font-bold shadow-xs transition-all hover:scale-105"
                title="ศูนย์ควบคุมระบบของผู้ดูแล (Admin Control Center)"
              >
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                <span>Admin Console</span>
              </button>

              <div class="relative">
                <button 
                  @click="userDropdownOpen = !userDropdownOpen" 
                  class="flex items-center gap-1.5 p-1 pl-2 pr-2.5 rounded-full hover:bg-gray-100 border border-gray-200 transition-all shadow-2xs bg-white"
                  :title="'ผู้ใช้งาน: ' + currentUser.name + ' (' + (isAdmin() ? 'admin' : currentUser.role) + ')'"
                >
                  <img :src="currentUser.avatar" class="w-6 h-6 rounded-full object-cover border border-gray-200 shadow-xs" />
                  <span class="text-xs font-semibold text-gray-700 max-w-[90px] truncate" x-text="currentUser.name"></span>
                  <span 
                    class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded-full tracking-wider border shadow-2xs"
                    :class="{
                      'bg-indigo-100 text-indigo-700 border-indigo-200': isAdmin(),
                      'bg-sky-100 text-sky-700 border-sky-200': !isAdmin() && isManager(),
                      'bg-emerald-100 text-emerald-700 border-emerald-200': !isAdmin() && !isManager() && isMember(),
                      'bg-purple-100 text-purple-700 border-purple-200': !isAdmin() && !isManager() && !isMember()
                    }"
                    x-text="isAdmin() ? 'ADMIN' : (currentUser.role || 'MEMBER').toUpperCase()"
                  ></span>
                  <i data-lucide="chevron-down" class="w-3 h-3 text-gray-400"></i>
                </button>

                <!-- User Dropdown Menu -->
                <div 
                  x-show="userDropdownOpen" 
                  x-transition:enter="transition ease-out duration-100"
                  x-transition:enter-start="transform opacity-0 scale-95"
                  x-transition:enter-end="transform opacity-100 scale-100"
                  x-transition:leave="transition ease-in duration-75"
                  x-transition:leave-start="transform opacity-100 scale-100"
                  x-transition:leave-end="transform opacity-0 scale-95"
                  class="absolute right-0 mt-2 w-64 bg-white rounded-xl shadow-xl border border-gray-200 py-2 z-50 text-xs divide-y divide-gray-100"
                  style="display: none;"
                >
                  <!-- Profile Info Header -->
                  <div class="px-4 py-2.5 flex items-center gap-2.5">
                    <img :src="currentUser.avatar" class="w-9 h-9 rounded-full object-cover border border-gray-200 shadow-2xs" />
                    <div class="truncate">
                      <div class="font-bold text-gray-900 truncate" x-text="currentUser.name"></div>
                      <div class="text-[11px] text-gray-500 truncate" x-text="currentUser.email"></div>
                      <div class="mt-0.5">
                        <span 
                          class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full"
                          :class="{
                            'bg-indigo-50 text-indigo-700': isAdmin(),
                            'bg-sky-50 text-sky-700': !isAdmin() && isManager(),
                            'bg-emerald-50 text-emerald-700': !isAdmin() && !isManager() && isMember(),
                            'bg-purple-50 text-purple-700': !isAdmin() && !isManager() && !isMember()
                          }"
                          x-text="'Role: ' + (isAdmin() ? 'ADMIN' : (currentUser.role || 'MEMBER').toUpperCase())"
                        ></span>
                      </div>
                    </div>
                  </div>

                <!-- Admin User Management Link -->
                <div class="py-1" x-show="isAdmin()">
                  <button 
                    @click="openUserManagementModal()" 
                    class="w-full text-left px-4 py-2 hover:bg-blue-50 text-blue-700 font-semibold flex items-center gap-2 transition-colors"
                  >
                    <i data-lucide="users" class="w-3.5 h-3.5 text-blue-600"></i>
                    <span>จัดการผู้ใช้งาน & กำหนดสิทธิ์</span>
                  </button>
                </div>
                </div>

                <!-- Sign Out -->
                <div class="py-1">
                  <button 
                    @click="logout()" 
                    class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600 font-semibold flex items-center gap-2 transition-colors"
                  >
                    <i data-lucide="log-out" class="w-3.5 h-3.5 text-red-500"></i>
                    <span>ออกจากระบบ</span>
                  </button>
                </div>
              </div>
            </div>
          </template>

        </div>
      </div>
    </header>

    <!-- BOARD VIEW TABS & TOOLBAR -->
    <div class="bg-white border-b border-[#e6e9ef] px-6 pt-2 shrink-0 flex items-center justify-between">
      <div class="flex items-center gap-6 text-xs font-semibold">
        <button 
          @click="activeTab = 'main_table'"
          class="pb-2 flex items-center gap-1.5 transition-colors border-b-2"
          :class="activeTab === 'main_table' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
        >
          <i data-lucide="table" class="w-3.5 h-3.5"></i>
          <span>Main table</span>
        </button>

        <button 
          @click="activeTab = 'gantt'"
          class="pb-2 flex items-center gap-1.5 transition-colors border-b-2"
          :class="activeTab === 'gantt' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
        >
          <i data-lucide="git-commit" class="w-3.5 h-3.5"></i>
          <span>Gantt</span>
        </button>

        <button 
          @click="activeTab = 'report'"
          class="pb-2 flex items-center gap-1.5 transition-colors border-b-2"
          :class="activeTab === 'report' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
        >
          <i data-lucide="bar-chart-3" class="w-3.5 h-3.5"></i>
          <span>Project Report</span>
        </button>
      </div>

      <div class="flex items-center gap-2 pb-2 text-xs text-gray-500">
        <!-- Save View Toolbar Button -->
        <button 
          @click="saveView()" 
          class="flex items-center gap-1.5 text-blue-700 hover:text-white bg-blue-50 hover:bg-blue-600 px-3 py-1 rounded-md border border-blue-200 shadow-2xs transition-all font-bold text-xs"
          :class="{'opacity-75 cursor-not-allowed': isSavingView}"
          :disabled="isSavingView"
          title="บันทึกข้อมูลและมุมมองของบอร์ด (Save View)"
        >
          <i data-lucide="save" class="w-3.5 h-3.5" x-show="!isSavingView"></i>
          <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin" x-show="isSavingView"></i>
          <span x-text="isSavingView ? 'บันทึก...' : 'Save View'"></span>
        </button>

        <!-- Active Sort Indicator pill if sorted -->
        <div x-show="sortColumn" class="flex items-center gap-1 bg-blue-50 text-blue-700 px-2 py-0.5 rounded text-[11px] font-semibold border border-blue-200">
          <span>Sort: <strong x-text="sortColumn === 'name' ? 'Task' : (sortColumn === 'update_count' ? 'Updates' : sortColumn)"></strong></span>
          <i data-lucide="arrow-up" class="w-3 h-3" x-show="sortDirection === 'asc'"></i>
          <i data-lucide="arrow-down" class="w-3 h-3" x-show="sortDirection === 'desc'"></i>
          <button @click="sortColumn = null; sortDirection = null" class="ml-1 hover:text-blue-900" title="ยกเลิกการ Sort">
            <i data-lucide="x" class="w-3 h-3"></i>
          </button>
        </div>

        <!-- Expand All Button -->
        <button 
          @click="expandAll()" 
          class="flex items-center gap-1.5 text-gray-700 hover:text-blue-700 bg-white hover:bg-blue-50 px-2.5 py-1 rounded-md border border-gray-200 shadow-2xs transition-colors font-bold text-xs"
          title="ขยายทุกกลุ่มงานและเปิดดู Subtasks ทั้งหมด (Expand All)"
        >
          <i data-lucide="chevrons-down" class="w-3.5 h-3.5 text-blue-600 font-bold"></i>
          <span>Expand All</span>
        </button>

        <!-- Collapse All Button -->
        <button 
          @click="collapseAll()" 
          class="flex items-center gap-1.5 text-gray-700 hover:text-blue-700 bg-white hover:bg-blue-50 px-2.5 py-1 rounded-md border border-gray-200 shadow-2xs transition-colors font-bold text-xs"
          title="พับเก็บทุกกลุ่มงานและซ่อน Subtasks (Collapse All)"
        >
          <i data-lucide="chevrons-up" class="w-3.5 h-3.5 text-gray-500 font-bold"></i>
          <span>Collapse All</span>
        </button>

        <!-- Manage Columns Button -->
        <button 
          @click="openAddColumnModal()" 
          class="flex items-center gap-1.5 text-gray-700 hover:text-blue-700 bg-white hover:bg-blue-50 px-2.5 py-1 rounded-md border border-gray-200 shadow-2xs transition-colors font-bold text-xs"
          title="จัดการและเพิ่มคอลัมน์ใหม่ (Manage Columns)"
        >
          <i data-lucide="columns-3" class="w-3.5 h-3.5 text-blue-600 font-bold"></i>
          <span>Columns</span>
        </button>

        <div class="h-4 w-[1px] bg-gray-300 mx-1"></div>

        <button class="flex items-center gap-1 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100 font-medium">
          <i data-lucide="filter" class="w-3.5 h-3.5"></i>
          <span>Filter</span>
        </button>
        <button @click="toggleSort('name')" class="flex items-center gap-1 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100 font-medium">
          <i data-lucide="arrow-up-down" class="w-3.5 h-3.5"></i>
          <span>Sort</span>
        </button>
        <button class="flex items-center gap-1 hover:text-gray-800 px-2 py-1 rounded hover:bg-gray-100 font-medium">
          <i data-lucide="eye-off" class="w-3.5 h-3.5"></i>
          <span>Hide</span>
        </button>
      </div>
    </div>

    <!-- VIEWER READ-ONLY NOTICE BANNER -->
    <div 
      x-show="isViewer()" 
      class="bg-purple-50 border-b border-purple-200 px-6 py-2 flex items-center justify-between text-xs text-purple-900 font-medium"
      style="display: none;"
    >
      <div class="flex items-center gap-2">
        <i data-lucide="eye" class="w-4 h-4 text-purple-600 shrink-0"></i>
        <span><strong>โหมดผู้เข้าชม (Viewer Mode):</strong> คุณสามารถดูข้อมูล ไทม์ไลน์ และรายงานความคืบหน้าได้ทั้งหมด แต่ไม่สามารถแก้ไข ลบ หรือย้ายงานได้</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-[11px] text-purple-700">ทดสอบสลับ Role:</span>
        <button 
          @click="mockLoginAs('admin')" 
          class="px-2 py-0.5 rounded bg-purple-200 hover:bg-purple-300 text-purple-900 font-bold text-[10px] transition-colors"
        >
          👑 Admin
        </button>
        <button 
          @click="mockLoginAs('manager')" 
          class="px-2 py-0.5 rounded bg-purple-200 hover:bg-purple-300 text-purple-900 font-bold text-[10px] transition-colors"
        >
          👔 Manager
        </button>
      </div>
    </div>

    <!-- MAIN BOARD DATA GRID -->
    <main class="flex-1 p-6 overflow-auto">
      
      <!-- Loading Skeleton -->
      <div x-show="isLoading" class="space-y-6 max-w-7xl mx-auto">
        <div class="animate-pulse flex space-x-4">
          <div class="h-8 bg-gray-200 rounded w-1/4"></div>
        </div>
        <div class="bg-white rounded-lg p-6 shadow-sm border border-gray-200 space-y-4">
          <div class="h-6 bg-gray-200 rounded w-full"></div>
          <div class="h-6 bg-gray-200 rounded w-full"></div>
          <div class="h-6 bg-gray-200 rounded w-full"></div>
        </div>
      </div>

      <!-- DATA GRID GROUPS (100% Ultra-Fast Reactive Architecture) -->
      <div x-show="!isLoading" class="space-y-8 min-w-[1200px] max-w-full">
        <template x-for="group in groups" :key="group.id">
          <div class="bg-white rounded-lg border border-[#e6e9ef] shadow-xs overflow-hidden">
            
            <!-- GROUP HEADER (Monday.com Style with Left Color Bar & Opening Timeline Bar) -->
            <div 
              class="px-4 py-2.5 flex items-center justify-between select-none cursor-pointer border-b border-[#e6e9ef] gap-4"
              :style="`border-left: 6px solid ${group.color || '#579BFC'}; background-color: #fafbfc;`"
              @click="toggleGroup(group.id)"
            >
              <!-- LEFT: Group Title, Tasks Count, Soft Opening, Grand Opening, Duration Bar (เรียงติดกันทางซ้าย) -->
              <div class="flex items-center gap-2.5 flex-wrap flex-1" @click.stop>
                <button @click="toggleGroup(group.id)" class="text-gray-500 hover:text-gray-700 focus:outline-none shrink-0">
                  <i 
                    data-lucide="chevron-down" 
                    class="w-4 h-4 transition-transform duration-200"
                    :class="{'rotate-[-90deg]': isGroupCollapsed(group.id)}"
                    :style="`color: ${group.color || '#1f2937'}`"
                  ></i>
                </button>
                <h2 @click="toggleGroup(group.id)" class="text-base font-extrabold tracking-tight shrink-0" x-text="group.title" :style="`color: ${group.color || '#1f2937'}`"></h2>
                <span class="text-xs font-bold text-gray-600 bg-gray-100 px-2.5 py-0.5 rounded-full border border-gray-200 shrink-0" x-text="(group.items ? group.items.length : 0) + ' Tasks'"></span>

                <div class="h-4 w-[1px] bg-gray-300 mx-0.5 shrink-0"></div>

                <!-- 1. Soft Opening Badge (ติดกับตัวเลข Task) -->
                <div 
                  @click="openGroupTimelinePopover(group, 'soft_opening')" 
                  class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold shadow-2xs border transition-all hover:scale-105 cursor-pointer shrink-0"
                  :class="getGroupOpeningDate(group, 'soft_opening') ? 'bg-amber-50 text-amber-900 border-amber-300 hover:bg-amber-100' : 'bg-gray-100 text-gray-400 border-dashed border-gray-300 hover:border-amber-400 hover:text-amber-700'"
                  title="คลิกเพื่อกำหนดหรือแก้ไขวัน Soft Opening ของสาขานี้"
                >
                  <span class="text-xs">🎀</span>
                  <span class="text-gray-500 font-semibold text-[11px]">Soft Opening:</span>
                  <span class="text-amber-900 font-extrabold" x-text="getGroupOpeningDate(group, 'soft_opening') ? formatOpeningDate(getGroupOpeningDate(group, 'soft_opening')) : '+ ตั้งวันที่'"></span>
                </div>

                <!-- 2. Grand Opening Badge -->
                <div 
                  @click="openGroupTimelinePopover(group, 'grand_opening')" 
                  class="flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold shadow-2xs border transition-all hover:scale-105 cursor-pointer shrink-0"
                  :class="getGroupOpeningDate(group, 'grand_opening') ? 'bg-purple-50 text-purple-900 border-purple-300 hover:bg-purple-100' : 'bg-gray-100 text-gray-400 border-dashed border-gray-300 hover:border-purple-400 hover:text-purple-700'"
                  title="คลิกเพื่อกำหนดหรือแก้ไขวัน Grand Opening ของสาขานี้"
                >
                  <span class="text-xs">🎊</span>
                  <span class="text-gray-500 font-semibold text-[11px]">Grand Opening:</span>
                  <span class="text-purple-900 font-extrabold" x-text="getGroupOpeningDate(group, 'grand_opening') ? formatOpeningDate(getGroupOpeningDate(group, 'grand_opening')) : '+ ตั้งวันที่'"></span>
                </div>

                <!-- 3. Duration Elapsed Progress Bar (ผ่านไปกี่วัน / เหลืออีกกี่วัน - เขียว เหลือง แดง) -->
                <div 
                  @click="openGroupTimelinePopover(group, 'timeline')"
                  class="min-w-[240px] max-w-[320px] bg-white px-3 py-1.5 rounded-xl border border-gray-200 shadow-2xs cursor-pointer hover:border-blue-400 transition-all group/gprog shrink-0"
                  :title="getGroupTimeElapsedInfo(group).tooltip + ' (คลิกเพื่อแก้ไขช่วงเวลา)'"
                >
                  <div class="flex items-center justify-between text-[10px] font-bold mb-1">
                    <span class="text-gray-700 truncate text-[10px]" x-text="getGroupTimeElapsedInfo(group).status_text"></span>
                    <span 
                      class="px-1.5 py-0.2 rounded-full font-black text-[9px] border shadow-2xs shrink-0 ml-1.5" 
                      :class="getGroupTimeElapsedInfo(group).badgeClass"
                      x-text="getGroupTimeElapsedInfo(group).remainingText"
                    ></span>
                  </div>
                  <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden relative border border-gray-200">
                    <div 
                      class="h-full transition-all duration-500 rounded-full shadow-2xs"
                      :class="getGroupTimeElapsedInfo(group).barClass"
                      :style="`width: ${getGroupTimeElapsedInfo(group).percent}%`"
                    ></div>
                  </div>
                </div>

              </div>

              <!-- Group Quick Actions -->
              <div class="flex items-center gap-2 shrink-0" @click.stop>
                <button 
                  @click="createItem(group)"
                  class="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-bold px-2.5 py-1 rounded hover:bg-blue-50 transition-colors"
                >
                  <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                  <span>เพิ่ม Task</span>
                </button>
              </div>
            </div>

            <!-- GROUP CONTENT -->
            <div x-show="!isGroupCollapsed(group.id)" class="overflow-x-auto">
              
              <!-- GRID HEADER -->
              <div class="flex items-center bg-[#f5f6f8] text-gray-700 text-xs font-bold uppercase tracking-wider sticky top-0 border-b border-[#e6e9ef] min-w-[1200px]">
                <div class="w-8 px-2 py-2 text-center border-r border-[#e6e9ef] shrink-0">
                  <input 
                    type="checkbox" 
                    :checked="isGroupAllSelected(group)" 
                    @change="toggleSelectAllGroup(group)"
                    class="rounded text-blue-600 border-gray-300 w-4 h-4 cursor-pointer"
                    title="เลือกทั้งหมดในกลุ่มนี้"
                  >
                </div>
                
                <!-- TASK COLUMN HEADER (Resizable) -->
                <div 
                  @click="toggleSort('name')"
                  class="px-3 py-2 border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/80 transition-colors select-none group/th flex items-center justify-between shrink-0 relative"
                  :style="`width: ${getColumnWidth('taskCol', 340)}px; min-width: ${getColumnWidth('taskCol', 340)}px;`"
                  title="คลิกเพื่อ Sort เรียงชื่อ ก-ฮ หรือ A-Z"
                >
                  <span class="font-extrabold">Task</span>
                  <div class="flex items-center text-gray-400 group-hover/th:text-gray-700">
                    <i data-lucide="arrow-up" class="w-3.5 h-3.5 text-blue-600 font-bold" x-show="sortColumn === 'name' && sortDirection === 'asc'"></i>
                    <i data-lucide="arrow-down" class="w-3.5 h-3.5 text-blue-600 font-bold" x-show="sortColumn === 'name' && sortDirection === 'desc'"></i>
                    <i data-lucide="arrow-up-down" class="w-3 h-3 opacity-0 group-hover/th:opacity-100" x-show="sortColumn !== 'name'"></i>
                  </div>
                  <!-- Column Resizer Handle -->
                  <div 
                    @mousedown.stop.prevent="startColumnResize('taskCol', $event)" 
                    class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-blue-500 transition-colors z-20"
                    title="ลากเพื่อขยาย/ลดความกว้างคอลัมน์นี้"
                  ></div>
                </div>

                <!-- UPDATES COLUMN HEADER -->
                <div 
                  @click="toggleSort('update_count')"
                  class="w-16 px-2 py-2 text-center border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/80 transition-colors select-none group/th shrink-0 flex items-center justify-center gap-1"
                  title="คลิกเพื่อ Sort ตามจำนวนการอัปเดต"
                >
                  <i data-lucide="message-square" class="w-3.5 h-3.5 text-gray-600"></i>
                  <i data-lucide="arrow-up" class="w-3 h-3 text-blue-600" x-show="sortColumn === 'update_count' && sortDirection === 'asc'"></i>
                  <i data-lucide="arrow-down" class="w-3 h-3 text-blue-600" x-show="sortColumn === 'update_count' && sortDirection === 'desc'"></i>
                </div>

                <!-- DYNAMIC MAIN HEADERS (Drag & Drop + Resizable) -->
                <template x-for="(col, cIdx) in mainColumns" :key="col.id">
                  <div 
                    draggable="true"
                    @dragstart="handleColumnDragStart(cIdx, $event)"
                    @dragover.prevent="handleColumnDragOver(cIdx, $event)"
                    @drop="handleColumnDrop(cIdx, $event)"
                    @dragend="handleColumnDragEnd()"
                    @click="toggleSort(col.id)"
                    class="px-2 py-2 text-center border-r border-[#e6e9ef] cursor-move hover:bg-gray-200/80 transition-colors select-none group/th shrink-0 flex items-center justify-between gap-1 relative"
                    :style="`width: ${getColumnWidth(col.id)}px; min-width: ${getColumnWidth(col.id)}px;`"
                    :title="'ลากเพื่อสลับตำแหน่ง หรือคลิกเพื่อ Sort เรียงตาม ' + col.title"
                  >
                    <div class="flex items-center gap-1 truncate flex-1 justify-center">
                      <i data-lucide="grip-vertical" class="w-2.5 h-2.5 text-gray-300 group-hover/th:text-gray-500 shrink-0"></i>
                      <span x-text="col.title" class="truncate font-bold"></span>
                      <i data-lucide="arrow-up" class="w-3 h-3 text-blue-600 shrink-0" x-show="sortColumn === col.id && sortDirection === 'asc'"></i>
                      <i data-lucide="arrow-down" class="w-3 h-3 text-blue-600 shrink-0" x-show="sortColumn === col.id && sortDirection === 'desc'"></i>
                    </div>
                    <button 
                      @click.stop="deleteColumn(col)" 
                      class="opacity-0 group-hover/th:opacity-100 p-0.5 text-gray-400 hover:text-red-600 rounded transition-opacity shrink-0" 
                      title="ลบคอลัมน์นี้"
                    >
                      <i data-lucide="trash-2" class="w-3 h-3"></i>
                    </button>
                    <!-- Column Resizer Handle -->
                    <div 
                      @mousedown.stop.prevent="startColumnResize(col.id, $event)" 
                      class="absolute right-0 top-0 bottom-0 w-1.5 cursor-col-resize hover:bg-blue-500 transition-colors z-20"
                      title="ลากเพื่อขยาย/ลดความกว้างคอลัมน์นี้"
                    ></div>
                  </div>
                </template>

                <!-- ADD COLUMN BUTTON IN HEADER (+) -->
                <div class="w-8 px-1 py-2 text-center shrink-0 flex items-center justify-center border-r border-[#e6e9ef]">
                  <button 
                    @click="openAddColumnModal()" 
                    class="w-6 h-6 rounded flex items-center justify-center text-gray-400 hover:text-blue-600 hover:bg-blue-100/70 transition-colors cursor-pointer" 
                    title="เพิ่มคอลัมน์ใหม่ (+ Add Column)"
                  >
                    <i data-lucide="plus" class="w-4 h-4"></i>
                  </button>
                </div>
              </div>

              <!-- ITEMS LIST -->
              <div class="divide-y divide-[#e6e9ef]">
                <template x-for="item in getSortedItems(group.items)" :key="item.id + '_' + boardRevision">
                  <div class="group/itemrow">
                    
                    <!-- 1. MAIN TASK ROW -->
                    <div 
                      class="flex items-center hover:bg-[#f5f6f8] transition-colors min-w-[1200px] text-xs h-10"
                      :class="{'bg-[#edf5ff] hover:bg-[#e4efff]': isItemSelected(item.id)}"
                    >
                      <!-- Checkbox -->
                      <div class="w-8 px-2 py-1.5 text-center bg-gray-50/30 shrink-0 flex items-center justify-center">
                        <input 
                          type="checkbox" 
                          :checked="isItemSelected(item.id)" 
                          @change="toggleSelectItem(item.id)"
                          class="rounded text-blue-600 border-gray-300 w-4 h-4 cursor-pointer"
                        >
                      </div>

                      <!-- Task Name with Expand Button -->
                      <div 
                        class="px-3 py-1.5 flex items-center gap-2 border-r border-[#e6e9ef] shrink-0"
                        :style="`width: ${getColumnWidth('taskCol', 340)}px; min-width: ${getColumnWidth('taskCol', 340)}px;`"
                      >
                        <!-- Chevron Expand / Collapse Button -->
                        <button 
                          type="button"
                          @click="toggleSubitems(item.id)"
                          class="flex items-center gap-1.5 px-2 py-1 rounded hover:bg-blue-100/80 transition-all cursor-pointer bg-gray-100 border border-gray-200 select-none"
                          :class="{'bg-blue-50 border-blue-300': isSubitemsExpanded(item.id)}"
                          :title="isSubitemsExpanded(item.id) ? 'คลิกเพื่อซ่อน Subtasks' : 'คลิกเพื่อกางดู Subtasks ทั้งหมด (' + (item.subitems ? item.subitems.length : 0) + ')'"
                        >
                          <i 
                            data-lucide="chevron-right" 
                            class="w-3.5 h-3.5 transition-transform duration-200"
                            :class="isSubitemsExpanded(item.id) ? 'rotate-90 text-blue-600 font-bold' : 'text-gray-500'"
                          ></i>
                          <span 
                            class="text-[10px] font-bold px-1.5 py-0.2 rounded-full"
                            :class="isSubitemsExpanded(item.id) ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'"
                            x-text="item.subitems ? item.subitems.length : 0"
                          ></span>
                        </button>

                        <!-- Editable Task Name -->
                        <input 
                          type="text" 
                          :value="item.name" 
                          @change="updateItemName(item, $event.target.value)"
                          class="flex-1 bg-transparent hover:bg-white focus:bg-white px-2 py-1 rounded text-sm font-bold text-gray-900 focus:ring-1 focus:ring-[#0073ea] outline-none border border-transparent focus:border-[#0073ea] transition-all truncate"
                        />
                      </div>

                      <!-- UPDATES COMMENT BUBBLE (💬 with number) -->
                      <div class="w-16 px-2 py-1.5 text-center border-r border-[#e6e9ef] shrink-0 flex items-center justify-center">
                        <button 
                          @click="openUpdatesDrawer(item)"
                          class="inline-flex items-center justify-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold transition-all shadow-2xs"
                          :class="item.update_count > 0 ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'text-gray-400 hover:text-blue-600 hover:bg-blue-50 border border-dashed border-gray-300'"
                          :title="item.update_count > 0 ? ('มี ' + item.update_count + ' ข้อความอัปเดตงาน') : 'คลิกเพื่อเพิ่มข้อความอัปเดตงาน'"
                        >
                          <i data-lucide="message-square" class="w-3 h-3"></i>
                          <span class="text-[11px]" x-text="item.update_count > 0 ? item.update_count : '+'"></span>
                        </button>
                      </div>

                      <!-- DYNAMIC MAIN COLUMNS -->
                      <template x-for="col in mainColumns" :key="col.id">
                        <div 
                          class="px-2 py-1 text-center border-r border-[#e6e9ef] shrink-0 relative flex items-center justify-center"
                          :style="`width: ${getColumnWidth(col.id)}px; min-width: ${getColumnWidth(col.id)}px;`"
                        >
                          
                          <!-- STATUS TYPE -->
                          <div x-show="col.type === 'status'" class="w-full relative">
                            <div 
                              class="status-badge rounded shadow-2xs w-full"
                              :style="getStatusStyle(item.column_values ? item.column_values[col.id] : '')"
                              @click="activeStatusPopover = (activeStatusPopover && activeStatusPopover.itemId === item.id && activeStatusPopover.colId === col.id) ? null : { itemId: item.id, colId: col.id, currentVal: item.column_values ? item.column_values[col.id] : '' }"
                            >
                              <span x-text="item.column_values ? item.column_values[col.id] || '-' : '-'" class="truncate px-2"></span>
                            </div>

                            <div 
                              x-show="activeStatusPopover && activeStatusPopover.itemId === item.id && activeStatusPopover.colId === col.id"
                              @click.away="activeStatusPopover = null"
                              class="absolute z-40 top-full left-1/2 -translate-x-1/2 mt-1 w-44 bg-white rounded-lg shadow-xl border border-gray-200 p-1.5 space-y-1"
                              style="display: none;"
                            >
                              <template x-for="preset in statusPresets" :key="preset.label">
                                <button 
                                  @click="updateCell(item, col.id, preset.label)"
                                  class="w-full text-center py-1.5 px-2 rounded text-xs font-semibold text-white transition-opacity hover:opacity-90 shadow-2xs"
                                  :style="`background-color: ${preset.bg}`"
                                >
                                  <span x-text="preset.label"></span>
                                </button>
                              </template>
                            </div>
                          </div>

                          <!-- OVERALL COMPLETE BADGE (e.g. 29/59 (49%)) -->
                          <div 
                            x-show="col.id === 'col_2' || (col.title && col.title.toLowerCase().includes('overall complete'))" 
                            class="w-full text-center"
                          >
                            <span 
                              class="inline-block px-2.5 py-0.5 rounded-full text-xs font-extrabold shadow-2xs"
                              :class="getOverallCompleteInfo(item).percent === 100 ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : (getOverallCompleteInfo(item).done > 0 ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-gray-100 text-gray-600 border border-gray-200')"
                              x-text="getOverallCompleteInfo(item).label"
                              :title="'เสร็จสิ้น ' + getOverallCompleteInfo(item).label"
                            ></span>
                          </div>

                          <!-- PROGRESS TYPE (Dynamic Progress By Dept & Progress Calculations) -->
                          <div 
                            x-show="col.type === 'progress' && col.id !== 'col_2' && !(col.title && col.title.toLowerCase().includes('overall complete'))" 
                            class="w-full flex items-center gap-1.5 px-1 group/prog cursor-pointer"
                            :title="getItemProgressInfo(item, col).tooltip"
                          >
                            <div class="flex-1 bg-gray-100 rounded-full h-3 border border-emerald-500 overflow-hidden relative shadow-2xs">
                              <div 
                                class="bg-emerald-500 h-full transition-all duration-300"
                                :style="`width: ${getItemProgressInfo(item, col).percent}%`"
                              ></div>
                            </div>
                            <span 
                              class="text-[11px] font-bold text-gray-800 w-9 text-right" 
                              x-text="getItemProgressInfo(item, col).percent + '%'"
                            ></span>
                          </div>

                          <!-- SOFT OPENING (Table Cell) -->
                          <div 
                            x-show="col.id === 'col_5' || (col.title && col.title.toLowerCase().includes('soft opening'))" 
                            class="w-full text-center cursor-pointer group/so"
                            @click="openTimelinePopover(item, false, null, 'col_5')"
                            title="คลิกเพื่อเลือกวัน Soft Opening"
                          >
                            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs text-gray-700 hover:bg-amber-50 hover:text-amber-800 border border-transparent hover:border-amber-200 transition-colors truncate max-w-full font-medium">
                              <span>🎀</span>
                              <span x-text="item.column_values && item.column_values[col.id] ? formatOpeningDate(item.column_values[col.id]) : '-'" class="truncate"></span>
                            </div>
                          </div>

                          <!-- GRAND OPENING (Table Cell) -->
                          <div 
                            x-show="col.id === 'col_6' || (col.title && col.title.toLowerCase().includes('grand opening'))" 
                            class="w-full text-center cursor-pointer group/go"
                            @click="openTimelinePopover(item, false, null, 'col_6')"
                            title="คลิกเพื่อเลือกวัน Grand Opening"
                          >
                            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs text-gray-700 hover:bg-purple-50 hover:text-purple-800 border border-transparent hover:border-purple-200 transition-colors truncate max-w-full font-medium">
                              <span>🎊</span>
                              <span x-text="item.column_values && item.column_values[col.id] ? formatOpeningDate(item.column_values[col.id]) : '-'" class="truncate"></span>
                            </div>
                          </div>

                          <!-- DURATION PROGRESS BAR (ผ่านไปกี่วัน / เหลืออีกกี่วัน - เขียว เหลือง แดง) -->
                          <div 
                            x-show="col.id === 'col_12' || (col.title && col.title.toLowerCase().includes('duration'))" 
                            class="w-full px-1 cursor-pointer group/dur"
                            @click="openTimelinePopover(item, false)"
                            :title="getTimeElapsedInfo(item).tooltip + ' (คลิกเพื่อแก้ไขช่วงเวลา)'"
                          >
                            <template x-if="getTimeElapsedInfo(item).hasData">
                              <div class="space-y-1">
                                <div class="flex items-center justify-between text-[10px] font-bold">
                                  <span class="text-gray-700 truncate text-[10px]" x-text="getTimeElapsedInfo(item).status_text"></span>
                                  <span 
                                    class="px-1.5 py-0.2 rounded-full font-black text-[9px] border shadow-2xs shrink-0 ml-1" 
                                    :class="getTimeElapsedInfo(item).badgeClass"
                                    x-text="getTimeElapsedInfo(item).remainingText"
                                  ></span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden relative shadow-2xs border border-gray-300">
                                  <div 
                                    class="h-full transition-all duration-500 rounded-full shadow-xs"
                                    :class="getTimeElapsedInfo(item).barClass"
                                    :style="`width: ${getTimeElapsedInfo(item).percent}%`"
                                  ></div>
                                </div>
                              </div>
                            </template>
                            <template x-if="!getTimeElapsedInfo(item).hasData">
                              <div class="text-center">
                                <span class="inline-block px-2.5 py-0.5 rounded-full bg-gray-100 text-gray-400 text-xs font-medium border border-dashed border-gray-300 hover:border-blue-400 hover:text-blue-600">
                                  + กำหนด Timeline
                                </span>
                              </div>
                            </template>
                          </div>

                          <!-- LAST UPDATED COLUMN (Shows Real Google Avatar, Updater Name & Timestamp) -->
                          <div 
                            x-show="col.id === 'col_17' || (col.title && col.title.toLowerCase().includes('last update'))" 
                            class="w-full flex items-center justify-center px-1"
                            :title="'อัปเดตล่าสุดโดย: ' + getItemLastUpdate(item, col.id).name + (getItemLastUpdate(item, col.id).time ? ' (' + getItemLastUpdate(item, col.id).time + ')' : '')"
                          >
                            <div class="inline-flex items-center gap-1.5 max-w-full py-0.5 px-1.5 rounded-lg hover:bg-gray-100/80 transition-colors cursor-default">
                              <!-- Avatar -->
                              <template x-if="getItemLastUpdate(item, col.id).avatar">
                                <img 
                                  :src="getItemLastUpdate(item, col.id).avatar" 
                                  class="w-5 h-5 rounded-full object-cover border border-gray-200 shadow-2xs shrink-0" 
                                />
                              </template>
                              <template x-if="!getItemLastUpdate(item, col.id).avatar && getItemLastUpdate(item, col.id).name !== '-'">
                                <div 
                                  class="w-5 h-5 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold text-[9px] flex items-center justify-center shadow-2xs shrink-0"
                                  x-text="getItemLastUpdate(item, col.id).initials"
                                ></div>
                              </template>

                              <!-- Info (Name & Time) -->
                              <div class="text-left min-w-0 leading-tight">
                                <div 
                                  class="text-[11px] font-bold text-gray-800 truncate max-w-[105px]"
                                  x-text="getItemLastUpdate(item, col.id).name"
                                ></div>
                                <div 
                                  class="text-[9px] text-gray-400 truncate max-w-[105px]"
                                  x-text="getItemLastUpdate(item, col.id).time"
                                ></div>
                              </div>
                            </div>
                          </div>

                          <!-- DATE / TIMELINE TYPE (Click to open Calendar) -->
                          <div 
                            x-show="col.type === 'date' && col.id !== 'col_5' && col.id !== 'col_6' && col.id !== 'col_12' && col.id !== 'col_17' && !(col.title && (col.title.toLowerCase().includes('soft opening') || col.title.toLowerCase().includes('grand opening') || col.title.toLowerCase().includes('duration') || col.title.toLowerCase().includes('last update')))" 
                            class="w-full text-center cursor-pointer group/datecell"
                            @click="openTimelinePopover(item, false)"
                            title="คลิกเพื่อเลือกวันที่จาก Calendar"
                          >
                            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs text-gray-700 hover:bg-blue-50 hover:text-blue-700 border border-transparent hover:border-blue-200 transition-colors truncate max-w-full font-medium">
                              <i data-lucide="calendar" class="w-3 h-3 text-gray-400 group-hover/datecell:text-blue-600 shrink-0"></i>
                              <span x-text="item.column_values ? (item.column_values[col.id] ? item.column_values[col.id].split(' ')[0] : '-') : '-'" class="truncate"></span>
                            </div>
                          </div>

                          <!-- OTHER TEXT / NUMBER -->
                          <div x-show="col.type !== 'status' && col.type !== 'progress' && col.type !== 'date' && col.id !== 'col_2' && col.id !== 'col_5' && col.id !== 'col_6' && col.id !== 'col_12' && col.id !== 'col_17' && !(col.title && (col.title.toLowerCase().includes('duration') || col.title.toLowerCase().includes('overall complete') || col.title.toLowerCase().includes('soft opening') || col.title.toLowerCase().includes('grand opening') || col.title.toLowerCase().includes('last update')))" class="w-full">
                            <input 
                              type="text" 
                              :value="item.column_values ? (item.column_values[col.id] ?? '') : ''"
                              @change="updateCell(item, col.id, $event.target.value)"
                              class="w-full text-center bg-transparent hover:bg-white focus:bg-white px-1 py-0.5 rounded text-xs text-gray-700 outline-none border border-transparent focus:border-blue-400 truncate"
                            />
                          </div>

                        </div>
                      </template>

                      <!-- Delete Row Button -->
                      <div class="w-8 px-2 py-1.5 text-center shrink-0">
                        <button 
                          @click="deleteItem(group, item)"
                          class="text-gray-300 hover:text-red-600 opacity-0 group-hover/itemrow:opacity-100 transition-opacity p-0.5"
                          title="ลบ Task"
                        >
                          <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                      </div>
                    </div>

                    <!-- 2. SUBITEMS EXPANDED MATRIX CONTAINER (Lazy Rendered with x-if for Instant Page Load) -->
                    <div 
                      x-show="isSubitemsExpanded(item.id)" 
                      class="py-3 pl-8 pr-4 bg-[#fafbfc] border-t border-b border-[#e6e9ef] transition-all"
                      :style="`border-left: 6px solid ${group.color || '#E2445C'};`"
                    >
                      <template x-if="isSubitemsExpanded(item.id)">
                        <div>
                          <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                              <span class="text-sm font-extrabold text-gray-900 flex items-center gap-1.5">
                                <i data-lucide="corner-down-right" class="w-4 h-4 text-blue-600"></i>
                                <span>Subtasks ของ: </span>
                                <strong class="text-blue-700 font-black" x-text="item.name"></strong>
                              </span>
                              <span class="text-[11px] font-bold text-gray-600 bg-white border border-gray-200 px-2.5 py-0.5 rounded-full" x-text="(item.subitems ? item.subitems.length : 0) + ' รายการ'"></span>
                            </div>

                            <button 
                              @click="createItem(group, item.id)"
                              class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1 bg-white border border-blue-200 px-2.5 py-1 rounded shadow-2xs hover:bg-blue-50 transition-colors"
                            >
                              <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                              <span>+ Add subtask</span>
                            </button>
                          </div>

                          <!-- Subitems Table -->
                          <div class="bg-white rounded-lg border border-gray-200 shadow-xs overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse monday-table">
                              <thead class="bg-[#f8f9fa] text-gray-700 text-xs font-bold border-b border-[#e6e9ef]">
                                <tr>
                                  <th class="w-8 px-2 py-2 text-center border-r border-[#e6e9ef]"><input type="checkbox" class="rounded text-blue-600 border-gray-300"></th>
                                  
                                  <!-- Subitem Name Sort -->
                                  <th 
                                    @click="toggleSubSort(item.id, 'name')"
                                    class="min-w-[320px] px-3 py-2 border-r border-[#e6e9ef] text-gray-700 cursor-pointer hover:bg-gray-200/70 select-none group/subth"
                                    title="คลิกเพื่อ Sort เรียงชื่อ Subitem (ก-ฮ หรือ A-Z)"
                                  >
                                    <div class="flex items-center justify-between">
                                      <span>Subitem</span>
                                      <div class="flex items-center text-gray-400">
                                        <i data-lucide="arrow-up" class="w-3 h-3 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'name' && getSubSortDirection(item.id) === 'asc'"></i>
                                        <i data-lucide="arrow-down" class="w-3 h-3 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'name' && getSubSortDirection(item.id) === 'desc'"></i>
                                        <i data-lucide="arrow-up-down" class="w-3 h-3 opacity-0 group-hover/subth:opacity-100" x-show="getSubSortColumn(item.id) !== 'name'"></i>
                                      </div>
                                    </div>
                                  </th>

                                  <!-- Updates Sort -->
                                  <th 
                                    @click="toggleSubSort(item.id, 'update_count')"
                                    class="w-14 px-2 py-2 text-center border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/70 select-none group/subth"
                                    title="คลิกเพื่อ Sort ตามจำนวนการอัปเดตงาน"
                                  >
                                    <div class="flex items-center justify-center gap-0.5">
                                      <i data-lucide="message-square" class="w-3.5 h-3.5 mx-auto text-gray-400"></i>
                                      <i data-lucide="arrow-up" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'update_count' && getSubSortDirection(item.id) === 'asc'"></i>
                                      <i data-lucide="arrow-down" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'update_count' && getSubSortDirection(item.id) === 'desc'"></i>
                                    </div>
                                  </th>

                                  <!-- No Sort -->
                                  <th 
                                    @click="toggleSubSort(item.id, 'sub_col_1')"
                                    class="w-14 px-2 py-2 text-center border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/70 select-none group/subth"
                                    title="คลิกเพื่อ Sort ตามลำดับข้อ (No)"
                                  >
                                    <div class="flex items-center justify-center gap-1">
                                      <span>No</span>
                                      <i data-lucide="arrow-up" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_1' && getSubSortDirection(item.id) === 'asc'"></i>
                                      <i data-lucide="arrow-down" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_1' && getSubSortDirection(item.id) === 'desc'"></i>
                                    </div>
                                  </th>

                                  <!-- Owner Sort -->
                                  <th 
                                    @click="toggleSubSort(item.id, 'sub_col_2')"
                                    class="w-24 px-2 py-2 text-center border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/70 select-none group/subth"
                                    title="คลิกเพื่อ Sort ตามผู้รับผิดชอบ (Owner)"
                                  >
                                    <div class="flex items-center justify-center gap-1">
                                      <span>Owner</span>
                                      <i data-lucide="arrow-up" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_2' && getSubSortDirection(item.id) === 'asc'"></i>
                                      <i data-lucide="arrow-down" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_2' && getSubSortDirection(item.id) === 'desc'"></i>
                                    </div>
                                  </th>

                                  <!-- Status Sort -->
                                  <th 
                                    @click="toggleSubSort(item.id, 'sub_col_3')"
                                    class="w-32 px-2 py-2 text-center border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/70 select-none group/subth"
                                    title="คลิกเพื่อ Sort ตามสถานะ (Status)"
                                  >
                                    <div class="flex items-center justify-center gap-1">
                                      <span>Status</span>
                                      <i data-lucide="arrow-up" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_3' && getSubSortDirection(item.id) === 'asc'"></i>
                                      <i data-lucide="arrow-down" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_3' && getSubSortDirection(item.id) === 'desc'"></i>
                                    </div>
                                  </th>

                                  <!-- Timeline Sort -->
                                  <th 
                                    @click="toggleSubSort(item.id, 'sub_col_4')"
                                    class="w-36 px-2 py-2 text-center border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/70 select-none group/subth"
                                    title="คลิกเพื่อ Sort ตาม Timeline"
                                  >
                                    <div class="flex items-center justify-center gap-1">
                                      <span>Timeline</span>
                                      <i data-lucide="arrow-up" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_4' && getSubSortDirection(item.id) === 'asc'"></i>
                                      <i data-lucide="arrow-down" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_4' && getSubSortDirection(item.id) === 'desc'"></i>
                                    </div>
                                  </th>

                                  <!-- Progress Sort -->
                                  <th 
                                    @click="toggleSubSort(item.id, 'sub_col_6')"
                                    class="w-36 px-2 py-2 text-center border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/70 select-none group/subth"
                                    title="คลิกเพื่อ Sort ตาม Progress %"
                                  >
                                    <div class="flex items-center justify-center gap-1">
                                      <span>Progress</span>
                                      <i data-lucide="arrow-up" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_6' && getSubSortDirection(item.id) === 'asc'"></i>
                                      <i data-lucide="arrow-down" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_6' && getSubSortDirection(item.id) === 'desc'"></i>
                                    </div>
                                  </th>

                                  <!-- Due Date Sort -->
                                  <th 
                                    @click="toggleSubSort(item.id, 'sub_col_7')"
                                    class="w-28 px-2 py-2 text-center border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/70 select-none group/subth"
                                    title="คลิกเพื่อ Sort ตามกำหนดส่ง (Due Date)"
                                  >
                                    <div class="flex items-center justify-center gap-1">
                                      <span>Due Date</span>
                                      <i data-lucide="arrow-up" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_7' && getSubSortDirection(item.id) === 'asc'"></i>
                                      <i data-lucide="arrow-down" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_7' && getSubSortDirection(item.id) === 'desc'"></i>
                                    </div>
                                  </th>

                                  <!-- Long Text Sort -->
                                  <th 
                                    @click="toggleSubSort(item.id, 'sub_col_8')"
                                    class="min-w-[160px] px-3 py-2 border-r border-[#e6e9ef] cursor-pointer hover:bg-gray-200/70 select-none group/subth"
                                    title="คลิกเพื่อ Sort ตาม Long Text"
                                  >
                                    <div class="flex items-center justify-between">
                                      <span>Long Text</span>
                                      <i data-lucide="arrow-up" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_8' && getSubSortDirection(item.id) === 'asc'"></i>
                                      <i data-lucide="arrow-down" class="w-2.5 h-2.5 text-blue-600 font-bold" x-show="getSubSortColumn(item.id) === 'sub_col_8' && getSubSortDirection(item.id) === 'desc'"></i>
                                    </div>
                                  </th>

                                  <!-- Last Updated Header -->
                                  <th 
                                    class="min-w-[140px] px-3 py-2 text-center border-r border-[#e6e9ef] select-none"
                                    title="ผู้ที่ทำการอัปเดตล่าสุด"
                                  >
                                    <div class="flex items-center justify-center gap-1 text-gray-500 font-bold">
                                      <i data-lucide="clock" class="w-3 h-3 text-gray-400"></i>
                                      <span>Last Updated</span>
                                    </div>
                                  </th>

                                  <th class="w-8 px-2 py-2 text-center"></th>
                                </tr>
                              </thead>
                              <tbody class="divide-y divide-[#e6e9ef]">
                                <template x-for="(sub, sIdx) in getSortedSubitems(item)" :key="sub.id + '_' + boardRevision">
                                  <tr 
                                    class="hover:bg-[#f5f6f8] transition-colors group/subrow"
                                    :class="{'bg-[#edf5ff] hover:bg-[#e4efff]': isItemSelected(sub.id)}"
                                  >
                                    <td class="px-2 py-1.5 text-center bg-gray-50/40">
                                      <input 
                                        type="checkbox" 
                                        :checked="isItemSelected(sub.id)" 
                                        @change="toggleSelectItem(sub.id)"
                                        class="rounded text-blue-600 border-gray-300 w-4 h-4 cursor-pointer"
                                      >
                                    </td>
                                    <td class="px-3 py-1.5">
                                      <input type="text" :value="sub.name" @change="updateItemName(sub, $event.target.value, item)" class="w-full bg-transparent hover:bg-white focus:bg-white px-2 py-1 rounded text-xs text-gray-800 border border-transparent focus:border-blue-400 outline-none font-medium truncate" />
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                      <button @click="openUpdatesDrawer(sub, item)" class="inline-flex items-center justify-center gap-1 px-1.5 py-0.5 rounded-full text-xs font-semibold" :class="sub.update_count > 0 ? 'bg-blue-600 text-white' : 'text-gray-400 hover:text-blue-600 border border-dashed border-gray-300'">
                                        <i data-lucide="message-square" class="w-3 h-3"></i>
                                        <span class="text-[10px]" x-text="sub.update_count > 0 ? sub.update_count : '+'"></span>
                                      </button>
                                    </td>
                                    <td class="px-2 py-1.5 text-center text-gray-500 font-mono text-[11px]" x-text="sub.column_values ? sub.column_values['sub_col_1'] || '' : ''"></td>
                                    <td class="px-2 py-1.5 text-center">
                                      <div class="flex items-center justify-center -space-x-1">
                                        <template x-for="av in getOwnerAvatars(sub.column_values ? sub.column_values['sub_col_2'] : '')" :key="av.name">
                                          <div class="w-6 h-6 rounded-full text-white text-[10px] font-bold flex items-center justify-center shadow-xs border border-white" :style="`background-color: ${av.color}`" :title="av.name" x-text="av.initials"></div>
                                        </template>
                                        <div x-show="getOwnerAvatars(sub.column_values ? sub.column_values['sub_col_2'] : '').length === 0" class="w-6 h-6 rounded-full border border-dashed border-gray-300 text-gray-400 flex items-center justify-center text-[10px]" title="Unassigned"><i data-lucide="user" class="w-3 h-3"></i></div>
                                      </div>
                                    </td>
                                    <td class="px-2 py-1.5 text-center relative">
                                      <div class="status-badge rounded text-[11px] h-7 shadow-2xs" :style="getStatusStyle(sub.column_values ? sub.column_values['sub_col_3'] : '')" @click="activeStatusPopover = (activeStatusPopover && activeStatusPopover.itemId === sub.id) ? null : { itemId: sub.id, colId: 'sub_col_3', currentVal: sub.column_values ? sub.column_values['sub_col_3'] : '' }">
                                        <span x-text="sub.column_values ? sub.column_values['sub_col_3'] || '-' : '-'" class="truncate px-2"></span>
                                      </div>
                                      <div x-show="activeStatusPopover && activeStatusPopover.itemId === sub.id" @click.away="activeStatusPopover = null" class="absolute z-40 top-full left-1/2 -translate-x-1/2 mt-1 w-44 bg-white rounded-lg shadow-xl border border-gray-200 p-1.5 space-y-1" style="display: none;">
                                        <template x-for="preset in statusPresets" :key="preset.label">
                                          <button @click="updateCell(sub, 'sub_col_3', preset.label, item)" class="w-full text-center py-1.5 px-2 rounded text-xs font-semibold text-white transition-opacity hover:opacity-90 shadow-2xs" :style="`background-color: ${preset.bg}`"><span x-text="preset.label"></span></button>
                                        </template>
                                      </div>
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                      <div 
                                        @click="openTimelinePopover(sub, true, item)"
                                        class="inline-flex items-center justify-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold text-white shadow-2xs truncate max-w-[160px] cursor-pointer hover:opacity-90 transition-opacity" 
                                        :style="`background-color: ${getTimelineInfo(sub.column_values ? sub.column_values['sub_col_4'] : null, sub.column_values ? sub.column_values['sub_col_5'] : null).text !== '-' ? '#579BFC' : '#C4C4C4'}`" 
                                        :title="getTimelineInfo(sub.column_values ? sub.column_values['sub_col_4'] : null, sub.column_values ? sub.column_values['sub_col_5'] : null).tooltip + ' (คลิกเพื่อแก้ไข Calendar)'" 
                                        x-text="getTimelineInfo(sub.column_values ? sub.column_values['sub_col_4'] : null, sub.column_values ? sub.column_values['sub_col_5'] : null).label"
                                      ></div>
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                      <div class="flex items-center gap-2 px-1">
                                        <div class="flex-1 bg-white rounded-full h-3 border border-emerald-500 overflow-hidden relative">
                                          <div class="bg-emerald-500 h-full transition-all duration-300" :style="`width: ${getProgressPercent(sub.column_values ? sub.column_values['sub_col_6'] : 0)}%`"></div>
                                        </div>
                                        <span class="text-[11px] font-semibold text-gray-700 w-8 text-right" x-text="getProgressPercent(sub.column_values ? sub.column_values['sub_col_6'] : 0) + '%'"></span>
                                      </div>
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                      <input type="text" :value="sub.column_values ? sub.column_values['sub_col_7'] || '' : ''" @change="updateCell(sub, 'sub_col_7', $event.target.value, item)" placeholder="-" class="w-full text-center bg-transparent hover:bg-white focus:bg-white px-1 py-0.5 rounded text-xs text-gray-700 outline-none border border-transparent focus:border-blue-400" />
                                    </td>
                                    <td class="px-3 py-1.5">
                                      <input type="text" :value="sub.column_values ? sub.column_values['sub_col_8'] || '' : ''" @change="updateCell(sub, 'sub_col_8', $event.target.value, item)" class="w-full bg-transparent hover:bg-white focus:bg-white px-1 py-0.5 rounded text-xs text-gray-700 outline-none border border-transparent focus:border-blue-400 truncate" />
                                    </td>
                                    <!-- Last Updated Cell -->
                                    <td class="px-2 py-1 text-center border-r border-[#e6e9ef]">
                                      <div 
                                        class="inline-flex items-center gap-1.5 max-w-full py-0.5 px-1.5 rounded-lg hover:bg-gray-100/80 transition-colors cursor-default"
                                        :title="'อัปเดตล่าสุดโดย: ' + getItemLastUpdate(sub, 'sub_col_10').name + (getItemLastUpdate(sub, 'sub_col_10').time ? ' (' + getItemLastUpdate(sub, 'sub_col_10').time + ')' : '')"
                                      >
                                        <template x-if="getItemLastUpdate(sub, 'sub_col_10').avatar">
                                          <img 
                                            :src="getItemLastUpdate(sub, 'sub_col_10').avatar" 
                                            class="w-4 h-4 rounded-full object-cover border border-gray-200 shadow-2xs shrink-0" 
                                          />
                                        </template>
                                        <template x-if="!parseLastUpdateInfo(sub.column_values ? sub.column_values['sub_col_10'] : '').avatar && parseLastUpdateInfo(sub.column_values ? sub.column_values['sub_col_10'] : '').name !== '-'">
                                          <div 
                                            class="w-4 h-4 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-bold text-[8px] flex items-center justify-center shadow-2xs shrink-0"
                                            x-text="parseLastUpdateInfo(sub.column_values ? sub.column_values['sub_col_10'] : '').initials"
                                          ></div>
                                        </template>
                                        <div class="text-left min-w-0 leading-tight">
                                          <div 
                                            class="text-[10px] font-bold text-gray-800 truncate max-w-[95px]"
                                            x-text="parseLastUpdateInfo(sub.column_values ? sub.column_values['sub_col_10'] : '').name"
                                          ></div>
                                          <div 
                                            class="text-[8px] text-gray-400 truncate max-w-[95px]"
                                            x-text="parseLastUpdateInfo(sub.column_values ? sub.column_values['sub_col_10'] : '').time"
                                          ></div>
                                        </div>
                                      </div>
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                      <button @click="deleteItem(group, sub, item)" class="text-gray-300 hover:text-red-600 opacity-0 group-hover/subrow:opacity-100 transition-opacity p-0.5"><i data-lucide="trash-2" class="w-3 h-3"></i></button>
                                    </td>
                                  </tr>
                                </template>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </template>
                    </div>

                  </div>
                </template>
              </div>

            </div>

          </div>
        </template>
      </div>
    </main>

    <!-- EXCEL IMPORT MODAL -->
    <div 
      x-show="isOpen" 
      class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
      style="display: none;"
    >
      <div 
        @click.away="!isUploading && closeModal()"
        class="bg-white rounded-xl shadow-2xl max-w-lg w-full p-6 border border-gray-100 relative transition-all"
      >
        <button @click="closeModal()" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <div class="flex items-center gap-3 mb-4">
          <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
            <i data-lucide="file-up" class="w-5 h-5"></i>
          </div>
          <div>
            <h3 class="text-base font-bold text-gray-900">นำเข้าไฟล์ Excel จาก Monday.com</h3>
            <p class="text-xs text-gray-500">แปลงและนำเข้า Groups, Main Items, Subitems และ Updates อัตโนมัติ</p>
          </div>
        </div>

        <div 
          @dragover.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="isDragging = false; handleFileSelect($event)"
          class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors"
          :class="isDragging ? 'border-emerald-500 bg-emerald-50' : 'border-gray-300 hover:border-gray-400 bg-gray-50'"
          @click="$refs.fileInput.click()"
        >
          <input type="file" x-ref="fileInput" @change="handleFileSelect($event)" accept=".xlsx" class="hidden" />
          <i data-lucide="upload-cloud" class="w-10 h-10 mx-auto mb-2 text-emerald-500"></i>
          <p class="text-sm font-medium text-gray-700" x-text="selectedFile ? selectedFile.name : 'ลากไฟล์ Excel (.xlsx) มาวางที่นี่ หรือคลิกเพื่อเลือกไฟล์'"></p>
          <p class="text-xs text-gray-400 mt-1" x-text="selectedFile ? (selectedFile.size / 1024 / 1024).toFixed(2) + ' MB' : 'รองรับไฟล์ Export มาตรฐานจาก Monday.com'"></p>
        </div>

        <div class="mt-4" x-show="selectedFile && !importResult">
          <label class="block text-xs font-semibold text-gray-700 mb-1">ตั้งชื่อ Board (ไม่บังคับ):</label>
          <input 
            type="text" 
            x-model="boardNameInput" 
            placeholder="ระบุชื่อ Board..."
            class="w-full px-3 py-1.5 text-xs border border-gray-300 rounded-md focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none"
          />
        </div>

        <div x-show="errorMessage" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md text-xs text-red-600 flex items-start gap-2">
          <i data-lucide="alert-circle" class="w-4 h-4 shrink-0 mt-0.5"></i>
          <span x-text="errorMessage"></span>
        </div>

        <div x-show="isUploading" class="mt-4">
          <div class="flex justify-between text-xs font-medium text-gray-600 mb-1">
            <span>กำลังแปลงและนำเข้าข้อมูลเข้าสู่ระบบ...</span>
            <span x-text="uploadProgress + '%'"></span>
          </div>
          <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
            <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" :style="`width: ${uploadProgress}%`"></div>
          </div>
        </div>

        <div x-show="importResult && importResult.success" class="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800">
          <div class="flex items-center gap-2 font-bold text-sm mb-2">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600"></i>
            <span>นำเข้าข้อมูลสำเร็จเรียบร้อย!</span>
          </div>
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="bg-white p-2 rounded border border-emerald-100">
              <span class="text-gray-500">Board:</span> <strong x-text="importResult.board_name"></strong>
            </div>
            <div class="bg-white p-2 rounded border border-emerald-100">
              <span class="text-gray-500">Groups:</span> <strong x-text="importResult.total_groups"></strong> กลุ่ม
            </div>
            <div class="bg-white p-2 rounded border border-emerald-100">
              <span class="text-gray-500">Main Items:</span> <strong x-text="importResult.total_main_items"></strong> รายการ
            </div>
            <div class="bg-white p-2 rounded border border-emerald-100">
              <span class="text-gray-500">Subitems:</span> <strong x-text="importResult.total_subitems"></strong> งานย่อย
            </div>
            <div class="bg-white p-2 rounded border border-emerald-100 col-span-2">
              <span class="text-gray-500">Activity Updates:</span> <strong x-text="importResult.total_updates"></strong> ข้อความ
            </div>
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
          <button 
            x-show="!importResult"
            @click="closeModal()" 
            :disabled="isUploading"
            class="px-4 py-1.5 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
          >
            ยกเลิก
          </button>
          <button 
            x-show="!importResult"
            @click="startImport()" 
            :disabled="!selectedFile || isUploading"
            class="px-4 py-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 rounded-md shadow-sm transition-colors flex items-center gap-1.5"
          >
            <i data-lucide="play" class="w-3.5 h-3.5" x-show="!isUploading"></i>
            <span x-text="isUploading ? 'กำลังประมวลผล...' : 'เริ่มนำเข้าข้อมูล'"></span>
          </button>
          <button 
            x-show="importResult"
            @click="closeModal()" 
            class="px-4 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-md shadow-sm transition-colors"
          >
            ดูข้อมูลในตาราง (Reload)
          </button>
        </div>
      </div>
    </div>

    <!-- MANAGE & ADD COLUMN MODAL -->
    <div 
      x-show="showColumnModal" 
      class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
      style="display: none;"
    >
      <div 
        @click.away="showColumnModal = false"
        class="bg-white rounded-2xl shadow-2xl max-w-xl w-full p-6 border border-gray-100 relative transition-all"
      >
        <button @click="showColumnModal = false" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>

        <div class="flex items-center gap-3 mb-5">
          <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shadow-xs">
            <i data-lucide="columns-3" class="w-5 h-5"></i>
          </div>
          <div>
            <h3 class="text-base font-extrabold text-gray-900">จัดการและเพิ่มคอลัมน์ (Manage Columns)</h3>
            <p class="text-xs text-gray-500">สร้างคอลัมน์ใหม่ หรือลบคอลัมน์ที่ไม่ต้องการออกจากบอร์ด</p>
          </div>
        </div>

        <!-- 1. Add New Column Form -->
        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 space-y-3 mb-5">
          <h4 class="text-xs font-bold text-gray-800 flex items-center gap-1.5">
            <i data-lucide="plus-circle" class="w-3.5 h-3.5 text-blue-600"></i>
            <span>เพิ่มคอลัมน์ใหม่</span>
          </h4>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-semibold text-gray-600 mb-1">ชื่อคอลัมน์ (Column Title):</label>
              <input 
                type="text" 
                x-model="newColumnTitle" 
                placeholder="เช่น งบประมาณ, Target Date, หมายเหตุ..." 
                class="w-full px-3 py-1.5 text-xs bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                @keyup.enter="createColumn()"
              />
            </div>

            <div>
              <label class="block text-[11px] font-semibold text-gray-600 mb-1">ประเภทข้อมูล (Column Type):</label>
              <select 
                x-model="newColumnType" 
                class="w-full px-3 py-1.5 text-xs bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
              >
                <template x-for="preset in columnTypePresets" :key="preset.type">
                  <option :value="preset.type" x-text="preset.label"></option>
                </template>
              </select>
            </div>
          </div>

          <div class="flex justify-end pt-1">
            <button 
              @click="createColumn()" 
              :disabled="!newColumnTitle.trim() || isSubmittingColumn"
              class="px-4 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-lg shadow-sm transition-all flex items-center gap-1.5"
            >
              <i data-lucide="plus" class="w-3.5 h-3.5"></i>
              <span x-text="isSubmittingColumn ? 'กำลังเพิ่ม...' : 'เพิ่มคอลัมน์นี้'"></span>
            </button>
          </div>
        </div>

        <!-- 2. Existing Columns List -->
        <div>
          <h4 class="text-xs font-bold text-gray-800 mb-2 flex items-center justify-between">
            <span>คอลัมน์ทั้งหมดในบอร์ด (<span x-text="mainColumns.length"></span>)</span>
            <span class="text-[10px] text-gray-400 font-normal">คลิกถังขยะเพื่อลบคอลัมน์</span>
          </h4>

          <div class="max-h-60 overflow-y-auto divide-y divide-gray-100 border border-gray-200 rounded-xl bg-white">
            <template x-for="(col, cIdx) in mainColumns" :key="col.id">
              <div class="flex items-center justify-between px-3 py-2 hover:bg-gray-50 transition-colors text-xs">
                <div class="flex items-center gap-2">
                  <span class="text-[10px] font-bold text-gray-400 w-4 text-center" x-text="cIdx + 1"></span>
                  <span class="font-bold text-gray-800" x-text="col.title"></span>
                  <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-gray-100 text-gray-500 border border-gray-200" x-text="col.type"></span>
                </div>

                <button 
                  @click="deleteColumn(col)" 
                  class="text-gray-400 hover:text-red-600 hover:bg-red-50 p-1.5 rounded-lg transition-colors"
                  title="ลบคอลัมน์นี้"
                >
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </div>
            </template>
          </div>
        </div>

        <div class="mt-5 flex justify-end">
          <button 
            @click="showColumnModal = false" 
            class="px-4 py-1.5 text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
          >
            ปิด
          </button>
        </div>
      </div>
    </div>

    <!-- UPDATES SLIDE-OVER DRAWER -->
    <div 
      x-show="showUpdatesDrawer" 
      class="fixed inset-0 z-50 overflow-hidden" 
      style="display: none;"
    >
      <div class="absolute inset-0 bg-black/40 backdrop-blur-2xs transition-opacity" @click="showUpdatesDrawer = false"></div>
      <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
        <div class="w-screen max-w-lg bg-white shadow-2xl flex flex-col border-l border-gray-200">
          
          <!-- Drawer Header with Counter -->
          <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shadow-xs">
                <i data-lucide="message-square" class="w-4 h-4"></i>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-sm font-bold text-gray-900 truncate max-w-[280px]" x-text="activeItemForUpdates ? activeItemForUpdates.name : 'Activity Updates'"></h3>
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700" x-text="itemUpdates.length + ' รายการ'"></span>
                </div>
                <p class="text-xs text-gray-500">ประวัติการอัปเดตงานและข้อความประสานงาน</p>
              </div>
            </div>
            <button @click="showUpdatesDrawer = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-200/60 transition-colors">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
          </div>

          <!-- New Update Input Box -->
          <div class="p-4 border-b border-gray-200 bg-white">
            <textarea 
              x-model="newUpdateContent" 
              placeholder="พิมพ์ข้อความอัปเดตงาน หรือประสานงานสาขา..." 
              rows="3"
              class="w-full text-xs p-3 border border-gray-200 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none resize-none"
            ></textarea>
            <div class="flex justify-end mt-2">
              <button 
                @click="submitUpdate()" 
                :disabled="!newUpdateContent.trim() || isSubmittingUpdate"
                class="px-4 py-1.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded-md shadow-sm transition-colors flex items-center gap-1.5"
              >
                <i data-lucide="send" class="w-3.5 h-3.5"></i>
                <span x-text="isSubmittingUpdate ? 'กำลังส่ง...' : 'Update'"></span>
              </button>
            </div>
          </div>

          <!-- Updates History List -->
          <div class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50/50">
            <template x-for="update in itemUpdates" :key="update.id || update.monday_post_id">
              <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-2xs space-y-2 hover:border-gray-300 transition-colors">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <template x-if="getUpdateAvatar(update)">
                      <img :src="getUpdateAvatar(update)" class="w-7 h-7 rounded-full object-cover shadow-xs border border-gray-200" :alt="getUpdateName(update)" />
                    </template>
                    <template x-if="!getUpdateAvatar(update)">
                      <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 font-bold text-xs flex items-center justify-center shadow-xs" x-text="getUpdateName(update).substring(0, 2).toUpperCase()"></div>
                    </template>
                    <span class="text-xs font-bold text-gray-800" x-text="getUpdateName(update)"></span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="text-[10px] text-gray-400" x-text="update.created_at || 'Recently'"></span>
                    <!-- Edit & Delete Action Buttons (for Admin, Manager, or author) -->
                    <div class="flex items-center gap-1 opacity-80 hover:opacity-100" x-show="isAdmin() || isManager() || (currentUser && currentUser.name === update.user_name)">
                      <button 
                        @click="startEditUpdate(update)" 
                        class="p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition-colors" 
                        title="แก้ไขข้อความ"
                      >
                        <i data-lucide="edit-2" class="w-3.5 h-3.5"></i>
                      </button>
                      <button 
                        @click="deleteUpdate(update)" 
                        class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors" 
                        title="ลบข้อความ"
                      >
                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Normal Content View -->
                <div x-show="editingUpdateId !== update.id">
                  <p class="text-xs text-gray-700 whitespace-pre-line leading-relaxed" x-text="update.content"></p>
                </div>

                <!-- Inline Edit Mode -->
                <div x-show="editingUpdateId === update.id" class="mt-2 space-y-2" style="display: none;">
                  <textarea 
                    x-model="editingUpdateContent" 
                    rows="2" 
                    class="w-full text-xs p-2.5 border border-blue-400 rounded-md focus:ring-1 focus:ring-blue-500 outline-none resize-none bg-blue-50/20"
                  ></textarea>
                  <div class="flex justify-end gap-1.5">
                    <button 
                      @click="cancelEditUpdate()" 
                      class="px-2.5 py-1 text-[11px] font-medium text-gray-600 hover:bg-gray-100 rounded transition-colors"
                    >
                      ยกเลิก
                    </button>
                    <button 
                      @click="saveEditUpdate(update)" 
                      :disabled="!editingUpdateContent.trim() || isSavingEditUpdate" 
                      class="px-3 py-1 text-[11px] font-bold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 rounded shadow-xs transition-colors flex items-center gap-1"
                    >
                      <span x-text="isSavingEditUpdate ? 'กำลังบันทึก...' : 'บันทึกแก้ไข'"></span>
                    </button>
                  </div>
                </div>
              </div>
            </template>
            <div x-show="itemUpdates.length === 0" class="text-center py-12 text-gray-400 text-xs">
              <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
              <span>ยังไม่มีบันทึกข้อความสำหรับรายการนี้</span>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- TIMELINE DATE PICKER MODAL -->
    <div 
      x-show="activeTimelinePopover" 
      class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs"
      style="display: none;"
    >
      <div 
        @click.away="activeTimelinePopover = null"
        class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-5 border border-gray-200 relative transition-all"
      >
        <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center shadow-xs">
              <i data-lucide="calendar" class="w-4 h-4"></i>
            </div>
            <div>
              <h4 class="text-xs font-extrabold text-gray-900">กำหนดช่วงเวลา (Timeline)</h4>
              <span class="text-[10px] text-gray-500 truncate max-w-[200px] block" x-text="activeTimelinePopover ? activeTimelinePopover.item.name : ''"></span>
            </div>
          </div>
          <button @click="activeTimelinePopover = null" class="text-gray-400 hover:text-gray-600">
            <i data-lucide="x" class="w-4 h-4"></i>
          </button>
        </div>

        <!-- Duration Badge Display -->
        <div class="mb-4 bg-blue-50 border border-blue-200 rounded-xl p-3 flex items-center justify-between">
          <span class="text-xs font-bold text-blue-900">ระยะเวลารวม (Duration):</span>
          <span class="px-2.5 py-1 rounded-lg bg-blue-600 text-white font-extrabold text-xs shadow-xs" x-text="activeTimelinePopover ? activeTimelinePopover.durationText || '0 วัน' : '0 วัน'"></span>
        </div>

        <!-- Start Date & End Date Inputs -->
        <div class="space-y-3 mb-5">
          <div>
            <label class="block text-[11px] font-bold text-gray-700 mb-1">วันเริ่มต้น (Start Date):</label>
            <input 
              type="date" 
              x-model="activeTimelinePopover.startDate" 
              @change="updatePopoverDates()"
              class="w-full px-3 py-1.5 text-xs bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
            />
          </div>

          <div>
            <label class="block text-[11px] font-bold text-gray-700 mb-1">วันสิ้นสุด (End Date):</label>
            <input 
              type="date" 
              x-model="activeTimelinePopover.endDate" 
              @change="updatePopoverDates()"
              class="w-full px-3 py-1.5 text-xs bg-white border border-gray-300 rounded-lg focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
            />
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-2">
          <button 
            @click="activeTimelinePopover = null"
            class="px-3.5 py-1.5 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
          >
            ยกเลิก
          </button>
          <button 
            @click="saveTimelinePopover()"
            class="px-4 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-all flex items-center gap-1.5"
          >
            <i data-lucide="check" class="w-3.5 h-3.5"></i>
            <span>บันทึก Timeline</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 5. FLOATING BULK DELETE ACTION BAR (Appears when checkbox is selected) -->
    <div 
      x-show="selectedItemIds.length > 0"
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0 translate-y-8 scale-95"
      x-transition:enter-end="opacity-100 translate-y-0 scale-100"
      x-transition:leave="transition ease-in duration-150"
      x-transition:leave-start="opacity-100 translate-y-0 scale-100"
      x-transition:leave-end="opacity-0 translate-y-8 scale-95"
      class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-[#1f2937] text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-4 border border-gray-700"
      style="display: none;"
    >
      <div class="flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-blue-600 text-white font-black text-xs flex items-center justify-center shadow-xs" x-text="selectedItemIds.length"></span>
        <span class="text-xs font-bold tracking-tight">รายการที่เลือก</span>
      </div>

      <div class="h-4 w-[1px] bg-gray-700"></div>

      <!-- Delete Button with Trash Bin -->
      <button 
        @click="deleteSelectedItems()"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 active:bg-red-800 text-white text-xs font-bold shadow-md transition-all hover:scale-105"
        title="ลบรายการที่เลือกทั้งหมด"
      >
        <i data-lucide="trash-2" class="w-4 h-4"></i>
        <span>ลบรายการที่เลือก (<span x-text="selectedItemIds.length"></span>)</span>
      </button>

      <!-- Clear Selection Button -->
      <button 
        @click="clearSelection()"
        class="text-xs text-gray-400 hover:text-white font-medium px-2 py-1 transition-colors"
        title="ยกเลิกการเลือก"
      >
        ยกเลิก
      </button>
    </div>

    <!-- 7. LOGIN & AUTH MODAL -->
    <div 
      x-show="showLoginModal" 
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition ease-in duration-150"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
      style="display: none;"
    >
      <div 
        @click.away="showLoginModal = false"
        class="bg-white rounded-2xl shadow-2xl border border-gray-100 w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-200"
      >
        <!-- Modal Header -->
        <div class="p-6 bg-gradient-to-r from-blue-600 to-indigo-700 text-white relative">
          <button 
            @click="showLoginModal = false" 
            class="absolute top-4 right-4 text-white/80 hover:text-white p-1 rounded-full hover:bg-white/10 transition-colors"
          >
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
          <div class="flex items-center gap-2.5 mb-2">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center font-black text-xl shadow-inner">
              N
            </div>
            <div>
              <h3 class="text-base font-bold">เข้าสู่ระบบ Nigiwai PM</h3>
              <p class="text-xs text-blue-100">ระบบบริหารโครงการขยายสาขา Nigiwai Group</p>
            </div>
          </div>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-5">
          <!-- Google Sign-In Option -->
          <div class="text-center space-y-3">
            <div class="text-xs font-semibold text-gray-700">เข้าสู่ระบบด้วย Google Account:</div>
            
            <div id="google-modal-signin-btn" class="flex justify-center min-h-[44px]"></div>

            <template x-if="!authConfig.google_client_id">
              <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-[11px] text-amber-800 text-left flex items-start gap-2">
                <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                <div>
                  <strong>ยังไม่ได้ระบุ Google Client ID:</strong> 
                  <span class="block text-amber-700 mt-0.5">คุณสามารถเลือกจำลองสิทธิ์ด้านล่างเพื่อทดสอบระบบได้ทันที หรือเข้าสู่ระบบเป็น Admin เพื่อไปใส่ Google Client ID</span>
                </div>
              </div>
            </template>
          </div>

          </div>
        </div>
      </div>
    </div>

    <!-- 8. USER MANAGEMENT & SETTINGS MODAL (Admin Only) -->
    <div 
      x-show="showUserManagementModal" 
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      x-transition:leave="transition ease-in duration-150"
      x-transition:leave-start="opacity-100"
      x-transition:leave-end="opacity-0"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
      style="display: none;"
    >
      <div 
        @click.away="showUserManagementModal = false"
        x-data="{ userMgmtTab: 'users' }"
        class="bg-white rounded-2xl shadow-2xl border border-gray-100 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200"
      >
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50/70">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-xs">
              <i data-lucide="users" class="w-5 h-5"></i>
            </div>
            <div>
              <h3 class="text-base font-bold text-gray-900">จัดการผู้ใช้งานและกำหนดสิทธิ์ (User Roles)</h3>
              <p class="text-xs text-gray-500">จัดการบทบาทพนักงาน และตั้งค่าการเชื่อมต่อ Google OAuth 2.0</p>
            </div>
          </div>
          <button 
            @click="showUserManagementModal = false" 
            class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-200/50 transition-colors"
          >
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>

        <!-- Navigation Tabs -->
        <div class="px-6 pt-3 border-b border-gray-200 flex gap-6 text-xs font-semibold overflow-x-auto">
          <button 
            @click="userMgmtTab = 'users'" 
            class="pb-2.5 flex items-center gap-1.5 border-b-2 transition-colors whitespace-nowrap"
            :class="userMgmtTab === 'users' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
          >
            <i data-lucide="users" class="w-4 h-4"></i>
            <span>ผู้ใช้งาน & กำหนดสิทธิ์ (<span x-text="userList.length"></span>)</span>
          </button>
          <button 
            @click="userMgmtTab = 'settings'" 
            class="pb-2.5 flex items-center gap-1.5 border-b-2 transition-colors whitespace-nowrap"
            :class="userMgmtTab === 'settings' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
          >
            <i data-lucide="shield" class="w-4 h-4"></i>
            <span>ความปลอดภัย & Google Sign-In</span>
          </button>
          <button 
            @click="userMgmtTab = 'board_structure'" 
            class="pb-2.5 flex items-center gap-1.5 border-b-2 transition-colors whitespace-nowrap"
            :class="userMgmtTab === 'board_structure' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
          >
            <i data-lucide="layers" class="w-4 h-4"></i>
            <span>โครงสร้างกลุ่มงาน (<span x-text="(board.groups || []).length"></span>)</span>
          </button>
          <button 
            @click="userMgmtTab = 'maintenance'" 
            class="pb-2.5 flex items-center gap-1.5 border-b-2 transition-colors whitespace-nowrap"
            :class="userMgmtTab === 'maintenance' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
          >
            <i data-lucide="database" class="w-4 h-4"></i>
            <span>สำรองข้อมูล & กู้คืนระบบ</span>
          </button>
        </div>

        <!-- Modal Body (Scrollable) -->
        <div class="p-6 overflow-y-auto flex-1 space-y-6">
          
          <!-- TAB 1: USERS LIST & ROLES -->
          <div x-show="userMgmtTab === 'users'" class="space-y-4">
            <div class="flex items-center justify-between">
              <div class="text-xs font-bold text-gray-700">รายชื่อผู้ใช้งานทั้งหมดและสิทธิ์เข้าถึง:</div>
              <button 
                @click="fetchUserList()" 
                class="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-semibold"
              >
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                <span>รีเฟรชรายชื่อ</span>
              </button>
            </div>

            <!-- Users Table -->
            <div class="border border-gray-200 rounded-xl overflow-hidden shadow-2xs">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 font-bold">
                  <tr>
                    <th class="py-2.5 px-4">ผู้ใช้งาน</th>
                    <th class="py-2.5 px-4">อีเมล</th>
                    <th class="py-2.5 px-4">บทบาท (Role)</th>
                    <th class="py-2.5 px-4 text-center">สถานะ</th>
                    <th class="py-2.5 px-4">ล็อกอินล่าสุด</th>
                    <th class="py-2.5 px-4 text-center">จัดการ</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <template x-for="u in userList" :key="u.id">
                    <tr class="hover:bg-gray-50/70 transition-colors">
                      <td class="py-2.5 px-4">
                        <div class="flex items-center gap-2.5">
                          <img :src="u.avatar" class="w-7 h-7 rounded-full object-cover border border-gray-200 shadow-xs" />
                          <span class="font-bold text-gray-900 truncate" x-text="u.name"></span>
                        </div>
                      </td>
                      <td class="py-2.5 px-4 text-gray-600 font-mono text-[11px]" x-text="u.email"></td>
                      <td class="py-2.5 px-4">
                        <select 
                          :value="u.role" 
                          @change="changeUserRole(u, $event.target.value)"
                          class="px-2.5 py-1 text-xs font-semibold rounded-lg border border-gray-300 focus:ring-1 focus:ring-blue-500 outline-none"
                          :class="{
                            'bg-indigo-50 text-indigo-700 border-indigo-200': u.role === 'admin',
                            'bg-sky-50 text-sky-700 border-sky-200': u.role === 'manager',
                            'bg-emerald-50 text-emerald-700 border-emerald-200': u.role === 'member',
                            'bg-purple-50 text-purple-700 border-purple-200': u.role === 'viewer'
                          }"
                        >
                          <option value="admin">👑 Admin</option>
                          <option value="manager">👔 Manager</option>
                          <option value="member">👷 Member</option>
                          <option value="viewer">👁️ Viewer</option>
                        </select>
                      </td>
                      <td class="py-2.5 px-4 text-center">
                        <button 
                          @click="toggleUserStatus(u)" 
                          class="px-2 py-0.5 rounded-full text-[10px] font-bold transition-colors"
                          :class="u.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'"
                          x-text="u.is_active ? 'เปิดใช้งาน' : 'ระงับชั่วคราว'"
                        ></button>
                      </td>
                      <td class="py-2.5 px-4 text-[11px] text-gray-500" x-text="u.last_login ? u.last_login.substring(0, 16) : 'ยังไม่เคยล็อกอิน'"></td>
                      <td class="py-2.5 px-4 text-center">
                        <button 
                          @click="deleteUser(u)" 
                          class="p-1 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                          title="ลบผู้ใช้งานออกจากระบบ"
                        >
                          <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                      </td>
                    </tr>
                  </template>
                  <template x-if="userList.length === 0">
                    <tr>
                      <td colspan="6" class="py-8 text-center text-gray-400 text-xs">
                        ยังไม่มีข้อมูลผู้ใช้งานในระบบ
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>

          <!-- TAB 2: GOOGLE SIGN-IN & SECURITY SETTINGS -->
          <div x-show="userMgmtTab === 'settings'" class="max-w-xl space-y-4">
            <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900 leading-relaxed">
              <strong>วิธีตั้งค่า Google OAuth 2.0:</strong>
              <ol class="list-decimal pl-4 mt-1.5 space-y-1 text-blue-800">
                <li>ไปที่ <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="underline font-bold">Google Cloud Console</a> และสร้าง Project</li>
                <li>สร้าง <strong>OAuth 2.0 Client ID</strong> เลือก Web application</li>
                <li>ระบุ Authorized JavaScript origins เป็น <code>https://www.nigiwaigroup.com</code></li>
                <li>คัดลอก <strong>Client ID</strong> มาวางในช่องด้านล่าง แล้วกดบันทึก</li>
              </ol>
            </div>

            <div class="space-y-3">
              <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">Google OAuth Client ID:</label>
                <input 
                  type="text" 
                  x-model="authSettingsForm.google_client_id" 
                  placeholder="เช่น 1234567890-abcdef.apps.googleusercontent.com"
                  class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 outline-none font-mono"
                />
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">จำกัดเฉพาะอีเมลองค์กร (Allowed Domain):</label>
                <input 
                  type="text" 
                  x-model="authSettingsForm.allowed_domain" 
                  placeholder="เช่น nigiwaigroup.com (เว้นว่างหากอนุญาตทุก Google Account)"
                  class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 outline-none"
                />
                <span class="text-[11px] text-gray-400 mt-0.5 block">หากระบุ ผู้ใช้จะต้องล็อกอินด้วยอีเมลที่ลงท้ายด้วยโดเมนนี้เท่านั้น</span>
              </div>

              <div>
                <label class="block text-xs font-bold text-gray-700 mb-1">บทบาทเริ่มต้นสำหรับพนักงานใหม่ (Default Role):</label>
                <select 
                  x-model="authSettingsForm.default_role" 
                  class="w-full px-3 py-2 text-xs border border-gray-300 rounded-lg focus:ring-1 focus:ring-blue-500 outline-none"
                >
                  <option value="member">👷 Member (พนักงานผู้รับผิดชอบ - เริ่มต้น)</option>
                  <option value="viewer">👁️ Viewer (ผู้เข้าชมอย่างเดียว)</option>
                  <option value="manager">👔 Manager (ผู้จัดการโครงการ)</option>
                </select>
              </div>

              <div class="pt-2">
                <button 
                  @click="saveAuthSettings()" 
                  class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-sm transition-colors flex items-center gap-1.5"
                >
                  <i data-lucide="save" class="w-3.5 h-3.5"></i>
                  <span>บันทึกการตั้งค่าระบบ</span>
                </button>
              </div>
            </div>
          </div>

          <!-- TAB 3: BOARD STRUCTURE & GROUPS -->
          <div x-show="userMgmtTab === 'board_structure'" class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="text-xs font-bold text-gray-800">โครงสร้างกลุ่มงาน (Groups / Project Phases)</h4>
                <p class="text-[11px] text-gray-500">จัดการขั้นตอนการทำงานหลักของโครงการ Nigiwai PM</p>
              </div>
              <button 
                @click="addNewGroup()" 
                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-2xs flex items-center gap-1.5 transition-colors"
              >
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                <span>เพิ่มกลุ่มงานใหม่</span>
              </button>
            </div>

            <div class="space-y-2.5">
              <template x-for="(group, gIndex) in (board.groups || [])" :key="group.id">
                <div class="p-3.5 border border-gray-200 rounded-xl bg-white shadow-2xs flex items-center justify-between gap-4">
                  <div class="flex items-center gap-3 flex-1 min-w-0">
                    <input 
                      type="color" 
                      :value="group.color || '#579BFC'"
                      @change="group.color = $event.target.value; autoSaveView();" 
                      class="w-7 h-7 rounded-lg cursor-pointer border-0 p-0 bg-transparent shrink-0" 
                      title="เลือกสีกราฟิกประจำกลุ่ม"
                    />
                    <input 
                      type="text" 
                      x-model="group.title"
                      @blur="autoSaveView()" 
                      class="font-bold text-xs text-gray-900 border border-transparent hover:border-gray-200 focus:border-blue-500 px-2 py-1 rounded-md outline-none flex-1"
                    />
                  </div>
                  <div class="flex items-center gap-3 shrink-0 text-xs text-gray-500">
                    <span><strong x-text="(group.items || []).length"></strong> รายการ</span>
                    <button 
                      @click="deleteGroup(group.id)" 
                      class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                      title="ลบกลุ่มงานนี้"
                    >
                      <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    </button>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- TAB 4: SYSTEM MAINTENANCE & BACKUP -->
          <div x-show="userMgmtTab === 'maintenance'" class="space-y-6 max-w-2xl">
            <!-- Backup & Export Section -->
            <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50 space-y-3">
              <div class="flex items-center gap-2 text-xs font-bold text-gray-800">
                <i data-lucide="download-cloud" class="w-4 h-4 text-blue-600"></i>
                <span>สำรองข้อมูลโครงการทั้งหมด (Full Backup)</span>
              </div>
              <p class="text-[11px] text-gray-500 leading-relaxed">
                ส่งออกข้อมูลบอร์ด รายการงาน กลุ่มงาน ประวัติอัปเดต และการตั้งค่าทั้งหมดออกมาเป็นไฟล์ JSON สำรองไว้เพื่อความปลอดภัย
              </p>
              <div class="flex items-center gap-3 pt-1">
                <button 
                  @click="exportBoardJson()" 
                  class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold shadow-xs flex items-center gap-1.5 transition-colors"
                >
                  <i data-lucide="download" class="w-3.5 h-3.5"></i>
                  <span>ดาวน์โหลดสำรองข้อมูล (.json)</span>
                </button>
                <button 
                  @click="exportToExcel()" 
                  class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs flex items-center gap-1.5 transition-colors"
                >
                  <i data-lucide="file-spreadsheet" class="w-3.5 h-3.5"></i>
                  <span>ส่งออกเป็น Excel (.xlsx)</span>
                </button>
              </div>
            </div>

            <!-- Restore Section -->
            <div class="p-4 border border-amber-200 bg-amber-50/30 rounded-xl space-y-3">
              <div class="flex items-center gap-2 text-xs font-bold text-amber-900">
                <i data-lucide="upload-cloud" class="w-4 h-4 text-amber-600"></i>
                <span>กู้คืนข้อมูลโครงการจากไฟล์สำรอง (Restore from JSON)</span>
              </div>
              <p class="text-[11px] text-amber-800 leading-relaxed">
                เลือกไฟล์ JSON สำรองเพื่อนำเข้าข้อมูลโครงการเดิมกลับคืนมา (ข้อมูลบอร์ดปัจจุบันจะถูกแทนที่ด้วยข้อมูลจากไฟล์สำรอง)
              </p>
              <div>
                <label class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow-xs cursor-pointer transition-colors">
                  <i data-lucide="upload" class="w-3.5 h-3.5"></i>
                  <span>เลือกไฟล์ JSON เพื่อกู้คืน</span>
                  <input type="file" accept=".json" @change="importBoardJson($event)" class="hidden" />
                </label>
              </div>
            </div>

            <!-- System Diagnostics -->
            <div class="p-4 border border-gray-200 rounded-xl bg-white space-y-2">
              <div class="text-xs font-bold text-gray-800 mb-2">สถานะระบบ (System Diagnostics):</div>
              <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="p-2.5 bg-gray-50 rounded-lg">
                  <span class="text-gray-400 block text-[10px] uppercase font-bold">โฮสต์ & ฐานข้อมูล</span>
                  <span class="font-semibold text-gray-800">MySQL Database Connected</span>
                </div>
                <div class="p-2.5 bg-gray-50 rounded-lg">
                  <span class="text-gray-400 block text-[10px] uppercase font-bold">จำนวนกลุ่มงาน</span>
                  <span class="font-semibold text-gray-800" x-text="(board.groups || []).length + ' กลุ่มงาน'"></span>
                </div>
                <div class="p-2.5 bg-gray-50 rounded-lg">
                  <span class="text-gray-400 block text-[10px] uppercase font-bold">จำนวนงานทั้งหมด</span>
                  <span class="font-semibold text-gray-800" x-text="totalItemsCount + ' รายการ'"></span>
                </div>
                <div class="p-2.5 bg-gray-50 rounded-lg">
                  <span class="text-gray-400 block text-[10px] uppercase font-bold">ผู้ดูแลสูงสุด</span>
                  <span class="font-semibold text-indigo-700 font-bold">Kraijate Sompong 👑</span>
                </div>
              </div>
            </div>
          </div>

        </div>

        </div>
      </div>
    </div>

    <!-- 6. FLOATING TOAST NOTIFICATION -->
    <div 
      x-show="toastMessage" 
      x-transition:enter="transition ease-out duration-300 transform"
      x-transition:enter-start="opacity-0 translate-y-4 scale-95"
      x-transition:enter-end="opacity-100 translate-y-0 scale-100"
      x-transition:leave="transition ease-in duration-200 transform"
      x-transition:leave-start="opacity-100 translate-y-0 scale-100"
      x-transition:leave-end="opacity-0 translate-y-4 scale-95"
      class="fixed bottom-6 right-6 z-50 bg-gray-900/95 backdrop-blur text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3 text-xs font-bold border border-gray-700 pointer-events-none"
      style="display: none;"
    >
      <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400"></i>
      <span x-text="toastMessage"></span>
    </div>

  </div>

  <!-- Scripts with Cache Busting -->
  <script src="assets/js/board-app.js?v=<?= time() ?>"></script>
  <script src="assets/js/excel-importer.js?v=<?= time() ?>"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
    document.addEventListener("alpine:initialized", () => {
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  </script>
</body>
</html>
