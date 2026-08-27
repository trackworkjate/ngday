<?php
declare(strict_types=1);

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/api/config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? null;

// Read JSON input or POST body
$rawInput = file_get_contents('php://input');
$input = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (!empty($_POST)) {
    $input = array_merge($input, $_POST);
}

// Process AJAX actions
if ($action === 'test_connection') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    try {
        $res = Database::testConnection($input);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'save_and_install') {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        // 1. Test connection first
        $test = Database::testConnection($input);
        if (!$test['success']) {
            echo json_encode(['success' => false, 'error' => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $test['error']], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 2. Save db_config.php
        $saved = Database::saveConfig($input);
        if (!$saved) {
            echo json_encode(['success' => false, 'error' => 'ไม่สามารถบันทึกไฟล์ config ได้ โปรดตรวจสอบ Permission ของโฟลเดอร์ api/config/'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $installedTables = false;
        $importedSeed = false;
        $errors = [];

        // 3. Run schema.sql if requested
        if (!empty($input['run_schema'])) {
            $schemaPath = __DIR__ . '/schema.sql';
            if (!file_exists($schemaPath)) {
                $schemaPath = __DIR__ . '/../schema.sql';
            }
            if (file_exists($schemaPath)) {
                try {
                    $pdo = Database::getConnection();
                    $sql = file_get_contents($schemaPath);
                    $pdo->exec($sql);
                    $installedTables = true;
                } catch (Throwable $e) {
                    $errors[] = 'Schema execution: ' . $e->getMessage();
                }
            }
        }

        // 4. Run seed_data.sql if requested
        if (!empty($input['run_seed'])) {
            $seedPath = __DIR__ . '/data/seed_data.sql';
            if (!file_exists($seedPath)) {
                $seedPath = __DIR__ . '/../data/seed_data.sql';
            }
            if (file_exists($seedPath)) {
                try {
                    $pdo = Database::getConnection();
                    $sql = file_get_contents($seedPath);
                    $pdo->exec($sql);
                    $importedSeed = true;
                } catch (Throwable $e) {
                    $errors[] = 'Seed execution: ' . $e->getMessage();
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'บันทึกการตั้งค่าฐานข้อมูลเรียบร้อยแล้ว!',
            'installed_tables' => $installedTables,
            'imported_seed' => $importedSeed,
            'errors' => $errors
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$currentConfig = Database::getConfig();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ตั้งค่าการเชื่อมต่อฐานข้อมูล - Nigiwai PM</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-[#f7f9fb] text-[#323338] min-h-screen flex flex-col justify-center items-center p-4">

  <div class="w-full max-w-xl bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden" x-data="dbSetupApp()" x-cloak>
    
    <!-- HEADER -->
    <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white text-center relative">
      <div class="w-14 h-14 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-inner">
        <i data-lucide="database" class="w-7 h-7 text-white"></i>
      </div>
      <h1 class="text-xl font-bold">ตั้งค่าการเชื่อมต่อฐานข้อมูล</h1>
      <p class="text-xs text-blue-100 mt-1">Database Configuration & Installation Wizard (cPanel / Shared Hosting)</p>
    </div>

    <!-- FORM BODY -->
    <div class="p-6 md:p-8 space-y-5">

      <!-- Status Alerts -->
      <div x-show="alert.show" :class="alert.type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'" class="p-4 rounded-xl border flex items-start gap-3 text-xs" style="display: none;">
        <i :data-lucide="alert.type === 'success' ? 'check-circle' : 'alert-circle'" class="w-5 h-5 shrink-0 mt-0.5"></i>
        <div class="flex-1">
          <p class="font-bold text-sm" x-text="alert.title"></p>
          <p class="mt-0.5 leading-relaxed" x-text="alert.message"></p>
        </div>
      </div>

      <form @submit.prevent="saveAndInstall()" class="space-y-4">
        
        <!-- Host & Port Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-700 mb-1">
              Database Host <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <i data-lucide="server" class="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
              <input 
                type="text" 
                x-model="form.host" 
                required 
                placeholder="localhost หรือ 127.0.0.1" 
                class="w-full pl-9 pr-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
              />
            </div>
            <p class="text-[10px] text-gray-400 mt-1">ปกติบน cPanel / DirectAdmin ให้ใช้ <code>localhost</code></p>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">
              Port
            </label>
            <input 
              type="text" 
              x-model="form.port" 
              placeholder="3306" 
              class="w-full px-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
            />
          </div>
        </div>

        <!-- Database Name -->
        <div>
          <label class="block text-xs font-bold text-gray-700 mb-1">
            Database Name (ชื่อฐานข้อมูล) <span class="text-rose-500">*</span>
          </label>
          <div class="relative">
            <i data-lucide="folder-git-2" class="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
            <input 
              type="text" 
              x-model="form.name" 
              required 
              placeholder="เช่น nigiwaig_mpm" 
              class="w-full pl-9 pr-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
            />
          </div>
        </div>

        <!-- Username & Password Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">
              Database Username <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
              <i data-lucide="user" class="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
              <input 
                type="text" 
                x-model="form.user" 
                required 
                placeholder="เช่น nigiwaig_mpm" 
                class="w-full pl-9 pr-3 py-2 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-gray-700 mb-1">
              Database Password
            </label>
            <div class="relative">
              <i data-lucide="key" class="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
              <input 
                :type="showPassword ? 'text' : 'password'" 
                x-model="form.pass" 
                placeholder="รหัสผ่านฐานข้อมูล" 
                class="w-full pl-9 pr-8 py-2 text-xs bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-all"
              />
              <button 
                type="button" 
                @click="showPassword = !showPassword" 
                class="absolute right-2.5 top-2.5 text-gray-400 hover:text-gray-600"
              >
                <i :data-lucide="showPassword ? 'eye-off' : 'eye'" class="w-4 h-4"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Automatic Installation Options -->
        <div class="bg-blue-50/60 p-4 rounded-xl border border-blue-100 space-y-2.5">
          <span class="text-xs font-bold text-blue-900 block mb-1">ตัวเลือกการติดตั้งอัตโนมัติ:</span>
          
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" x-model="form.run_schema" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-gray-300">
            <span class="text-xs text-gray-700 font-medium">สร้างตารางฐานข้อมูลอัตโนมัติ (Execute <code>schema.sql</code>)</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" x-model="form.run_seed" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-gray-300">
            <span class="text-xs text-gray-700 font-medium">นำเข้าข้อมูลเริ่มต้น 12 กลุ่มงานจาก Monday.com (Execute <code>seed_data.sql</code>)</span>
          </label>
        </div>

        <!-- Action Buttons -->
        <div class="pt-2 flex flex-col md:flex-row items-center gap-3">
          <!-- Test Connection Button -->
          <button 
            type="button" 
            @click="testConnection()" 
            :disabled="isTesting || isSaving"
            class="w-full md:w-auto px-5 py-2.5 text-xs font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 active:bg-gray-300 disabled:opacity-50 rounded-xl transition-all flex items-center justify-center gap-2 border border-gray-200"
          >
            <i data-lucide="activity" class="w-4 h-4 text-blue-600" :class="{'animate-spin': isTesting}"></i>
            <span x-text="isTesting ? 'กำลังทดสอบ...' : 'ทดสอบการเชื่อมต่อ'"></span>
          </button>

          <!-- Save & Install Button -->
          <button 
            type="submit" 
            :disabled="isTesting || isSaving"
            class="w-full md:flex-1 px-6 py-2.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 active:bg-blue-800 disabled:opacity-50 rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2"
          >
            <i data-lucide="check" class="w-4 h-4" x-show="!isSaving"></i>
            <i data-lucide="loader-2" class="w-4 h-4 animate-spin" x-show="isSaving"></i>
            <span x-text="isSaving ? 'กำลังบันทึกและติดตั้ง...' : 'บันทึกการตั้งค่า & เข้าสู่ระบบ'"></span>
          </button>
        </div>

      </form>

      <!-- Back / Direct Link -->
      <div class="text-center pt-2 border-t border-gray-100">
        <a href="index.php" class="text-xs text-gray-500 hover:text-blue-600 flex items-center justify-center gap-1">
          <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
          <span>กลับไปยังหน้าหลัก (Data Grid)</span>
        </a>
      </div>

    </div>

  </div>

  <script>
    document.addEventListener('alpine:init', () => {
      Alpine.data('dbSetupApp', () => ({
        form: {
          host: '<?= addslashes($currentConfig['host']) ?>',
          port: '<?= addslashes($currentConfig['port']) ?>',
          name: '<?= addslashes($currentConfig['name']) ?>',
          user: '<?= addslashes($currentConfig['user']) ?>',
          pass: '<?= addslashes($currentConfig['pass']) ?>',
          run_schema: true,
          run_seed: true
        },
        showPassword: false,
        isTesting: false,
        isSaving: false,
        alert: { show: false, type: 'success', title: '', message: '' },

        async testConnection() {
          this.isTesting = true;
          this.alert.show = false;

          try {
            const res = await fetch('setup.php?action=test_connection', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(this.form)
            });
            const text = await res.text();
            let data;
            try {
              data = JSON.parse(text);
            } catch (err) {
              this.showAlert('error', 'การตอบกลับจาก Server ไม่ถูกต้อง', text || 'Server ส่งค่าว่างกลับมา');
              return;
            }

            if (data.success) {
              this.showAlert('success', 'เชื่อมต่อสำเร็จ!', 'ระบบสามารถติดต่อกับ MySQL Database ได้อย่างถูกต้องสมบูรณ์');
            } else {
              this.showAlert('error', 'เชื่อมต่อไม่สำเร็จ', data.error || 'โปรดตรวจสอบ Host, Port, Database, User หรือ Password');
            }
          } catch (e) {
            this.showAlert('error', 'เกิดข้อผิดพลาด', e.message);
          } finally {
            this.isTesting = false;
            this.$nextTick(() => lucide.createIcons());
          }
        },

        async saveAndInstall() {
          this.isSaving = true;
          this.alert.show = false;

          try {
            const res = await fetch('setup.php?action=save_and_install', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(this.form)
            });
            const text = await res.text();
            let data;
            try {
              data = JSON.parse(text);
            } catch (err) {
              this.showAlert('error', 'การตอบกลับจาก Server ไม่ถูกต้อง', text || 'Server ส่งค่าว่างกลับมา');
              return;
            }

            if (data.success) {
              this.showAlert('success', 'ติดตั้งและบันทึกข้อมูลสำเร็จ!', 'กำลังพาท่านเข้าสู่หน้ากระดานโครงการใน 2 วินาที...');
              setTimeout(() => {
                window.location.href = 'index.php';
              }, 2000);
            } else {
              this.showAlert('error', 'การติดตั้งไม่สำเร็จ', data.error || 'โปรดตรวจสอบข้อมูล');
            }
          } catch (e) {
            this.showAlert('error', 'เกิดข้อผิดพลาด', e.message);
          } finally {
            this.isSaving = false;
            this.$nextTick(() => lucide.createIcons());
          }
        },

        showAlert(type, title, message) {
          this.alert = { show: true, type, title, message };
        }
      }));
    });

    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });
  </script>
</body>
</html>
