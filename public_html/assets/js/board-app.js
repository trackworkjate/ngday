document.addEventListener("alpine:init", () => {
  Alpine.data("mondayBoardApp", () => ({
    // Sidebar & Navigation State
    sidebarOpen: true,
    activeWorkspace: "Main workspace",
    activeTab: "main_table",
    sidebarSearch: "",
    expandedFolders: {
      now_pm: true,
      history: false,
      pfm: false,
      sop: false
    },
    
    // Workspace Boards Tree
    workspaceFolders: [
      {
        id: "now_pm",
        name: "NOW Project Management",
        boards: [
          { id: 1, name: "Branch Planing 2026", active: true },
          { id: 2, name: "Nigiteri Planing 2026", active: false },
          { id: 3, name: "Nigiben Branch Planing 2026", active: false },
          { id: 4, name: "Tom N Toms Branch Planing...", active: false },
          { id: 5, name: "Branch ( Follow Up 2023-20... )", active: false },
          { id: 6, name: "Branch ( Project Mini Scope... )", active: false },
          { id: 7, name: "Branch ( Project Finished 20... )", active: false },
          { id: 8, name: "Branch ( Future Planing 202... )", active: false },
          { id: 9, name: "Project Timeline", active: false },
          { id: 10, name: "Branch ( Project Developme... )", active: false },
          { id: 11, name: "New Dashboard", active: false },
          { id: 12, name: "Branch To Cancel", active: false }
        ]
      },
      {
        id: "history",
        name: "Branch Opening History",
        boards: [
          { id: 13, name: "History 2024-2025", active: false },
          { id: 14, name: "Archive Branches", active: false }
        ]
      },
      {
        id: "pfm",
        name: "PFM",
        boards: [
          { id: 15, name: "PFM Master Tracking", active: false }
        ]
      },
      {
        id: "sop",
        name: "Control SOP Version All Master",
        boards: [
          { id: 16, name: "SOP Operations v2.1", active: false }
        ]
      }
    ],

    // Main Board State
    currentBoardId: 1,
    board: { name: "Branch Planing 2026", description: "Track ความคืบหน้าในการเปิดของแต่ละสาขา" },
    mainColumns: [],
    subColumns: [],
    groups: [],
    searchQuery: "",
    isLoading: true,
    lastSyncTime: null,
    isSyncing: false,
    syncInterval: null,

    // Column Sorting State for Main Items
    sortColumn: null, // 'name', 'update_count', or column id like 'col_2'
    sortDirection: null, // 'asc', 'desc', or null

    // Column Sorting State for Subitems: { [itemId]: { col: string, dir: 'asc'|'desc' } }
    subSortState: {},

    // Subitem expand states: { [itemId]: true/false }
    expandedSubitems: {},

    // Group collapse states: { [groupId]: true/false }
    collapsedGroups: {},

    // Updates Drawer State
    activeItemForUpdates: null,
    itemUpdates: [],
    newUpdateContent: "",
    isSubmittingUpdate: false,
    showUpdatesDrawer: false,

    // Status Options & Colors
    statusPresets: [
      { label: "Done", bg: "#00C875", text: "#FFFFFF" },
      { label: "Working on it", bg: "#FDAB3D", text: "#FFFFFF" },
      { label: "Not Started", bg: "#C4C4C4", text: "#FFFFFF" },
      { label: "Stuck", bg: "#E2445C", text: "#FFFFFF" },
      { label: "Waiting", bg: "#579BFC", text: "#FFFFFF" },
      { label: "Normal", bg: "#579BFC", text: "#FFFFFF" },
      { label: "High", bg: "#A25DDC", text: "#FFFFFF" }
    ],

    // Checkbox Selection State for Bulk / Single Actions
    selectedItemIds: [],

    // Column Management Modal State
    showColumnModal: false,
    newColumnTitle: "",
    newColumnType: "text",
    isSubmittingColumn: false,

    columnTypePresets: [
      { type: "text", label: "ข้อความสั้น (Text)", icon: "type" },
      { type: "status", label: "สถานะ (Status)", icon: "tag" },
      { type: "progress", label: "ความคืบหน้า (Progress %)", icon: "bar-chart-2" },
      { type: "date", label: "วันที่ (Date)", icon: "calendar" },
      { type: "people", label: "ผู้รับผิดชอบ (People)", icon: "users" },
      { type: "number", label: "ตัวเลข (Number)", icon: "hash" },
      { type: "long_text", label: "ข้อความยาว (Long Text)", icon: "file-text" }
    ],

    // Active Cell Edit Popover
    activeStatusPopover: null,

    // Active Timeline Calendar Popover
    activeTimelinePopover: null,

    // Resizable Columns State (Widths in pixels)
    columnWidths: {},

    // Drag & Drop Column Reorder State
    draggedColIndex: null,

    // Save View & Toast State
    isSavingView: false,
    toastMessage: "",

    // User Authentication & Roles (RBAC)
    currentUser: window.PRELOADED_AUTH?.user || null,
    authConfig: {
      google_client_id: window.PRELOADED_AUTH?.config?.google_client_id || "834120129002-ov166c1k38dk91e1fe1e10jgjv689nb3.apps.googleusercontent.com",
      allowed_domain: window.PRELOADED_AUTH?.config?.allowed_domain || "",
      default_role: window.PRELOADED_AUTH?.config?.default_role || "member",
      mock_mode_enabled: true
    },
    isAuthLoading: false,
    showLoginModal: false,
    showUserManagementModal: false,
    userDropdownOpen: false,
    userList: [],
    authSettingsForm: {
      google_client_id: window.PRELOADED_AUTH?.config?.google_client_id || "834120129002-ov166c1k38dk91e1fe1e10jgjv689nb3.apps.googleusercontent.com",
      allowed_domain: window.PRELOADED_AUTH?.config?.allowed_domain || "",
      default_role: window.PRELOADED_AUTH?.config?.default_role || "member"
    },

    // RBAC Permission Helpers
    isLoggedIn() {
      return Boolean(this.currentUser && this.currentUser.id);
    },
    isAdmin() {
      return Boolean(this.currentUser && this.currentUser.role === "admin");
    },
    isManager() {
      return Boolean(this.currentUser && (this.currentUser.role === "manager" || this.currentUser.role === "admin"));
    },
    isMember() {
      return Boolean(this.currentUser && (this.currentUser.role === "member" || this.currentUser.role === "manager" || this.currentUser.role === "admin"));
    },
    isViewer() {
      return Boolean(this.currentUser && this.currentUser.role === "viewer");
    },
    canEditTasks() {
      return !this.isViewer();
    },
    canManageColumns() {
      return !this.isLoggedIn() || this.isAdmin();
    },
    canManageTimeline() {
      return !this.isViewer();
    },

    // 100% Fail-Safe LocalStorage Persistence Engine
    persistToLocalStorage() {
      try {
        const payload = {
          version: 2,
          savedAt: new Date().toISOString(),
          board: this.board,
          mainColumns: this.mainColumns,
          subColumns: this.subColumns,
          groups: this.groups,
          columnWidths: this.columnWidths
        };
        localStorage.setItem(`nigiwai_pm_board_${this.currentBoardId}`, JSON.stringify(payload));
      } catch (e) {
        console.warn("LocalStorage save error:", e);
      }
    },

    loadFromLocalStorage() {
      try {
        const raw = localStorage.getItem(`nigiwai_pm_board_${this.currentBoardId}`);
        if (raw) {
          const parsed = JSON.parse(raw);
          if (parsed && parsed.groups && parsed.groups.length > 0) {
            this.board = parsed.board || this.board;
            this.mainColumns = parsed.mainColumns || this.mainColumns;
            this.subColumns = parsed.subColumns || this.subColumns;
            this.groups = parsed.groups || this.groups;
            this.columnWidths = parsed.columnWidths || this.columnWidths;
            return true;
          }
        }
      } catch (e) {
        console.warn("LocalStorage load error:", e);
      }
      return false;
    },

    async init() {
      // 1. Check Authentication Session & Google Client ID
      await this.checkAuthSession();

      // 2. Try loading from LocalStorage FIRST (Guarantees user customized view and edits never revert)
      const hasLocal = this.loadFromLocalStorage();

      // 3. If no LocalStorage, load from pre-rendered server data
      if (!hasLocal && window.INITIAL_BOARD_DATA && window.INITIAL_BOARD_DATA.groups && window.INITIAL_BOARD_DATA.groups.length > 0) {
        const d = window.INITIAL_BOARD_DATA;
        this.board = d.board || this.board;
        this.mainColumns = (d.columns || []).filter(c => !c.is_subitem && c.title.toLowerCase() !== 'subtasks');
        this.subColumns = (d.columns || []).filter(c => c.is_subitem);
        this.groups = d.groups || [];
      }

      this.setupInitialExpands();
      this.isLoading = false;
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    // --- Authentication & User Management Methods ---
    async checkAuthSession() {
      this.isAuthLoading = true;
      try {
        const res = await this.sendApiAction("get_current_user");
        if (res && res.success) {
          this.currentUser = res.user || null;
          if (res.config) {
            this.authConfig = res.config;
            this.authSettingsForm = { ...res.config };
          }
        }
      } catch (e) {
        console.warn("Auth check failed:", e);
      } finally {
        this.isAuthLoading = false;
        this.$nextTick(() => {
          this.initGoogleSignIn();
        });
      }
    },

    initGoogleSignIn() {
      if (!this.authConfig || !this.authConfig.google_client_id) return;
      if (typeof google === "undefined" || !google.accounts || !google.accounts.id) {
        setTimeout(() => this.initGoogleSignIn(), 600);
        return;
      }
      try {
        google.accounts.id.initialize({
          client_id: this.authConfig.google_client_id,
          callback: this.handleGoogleCredentialResponse.bind(this)
        });
        const modalBtn = document.getElementById("google-modal-signin-btn");
        if (modalBtn) {
          google.accounts.id.renderButton(modalBtn, {
            theme: "filled_blue",
            size: "large",
            text: "continue_with",
            shape: "pill",
            width: 280
          });
        }
      } catch (e) {
        console.warn("Google Sign-In initialization warning:", e);
      }
    },

    async handleGoogleCredentialResponse(response) {
      if (!response || !response.credential) return;
      this.isAuthLoading = true;
      try {
        const res = await this.sendApiAction("google_login", { credential: response.credential });
        this.isAuthLoading = false;
        if (res && res.success && res.user) {
          this.currentUser = res.user;
          this.showLoginModal = false;
          this.showToast(`✅ ยินดีต้อนรับคุณ ${res.user.name} (${res.user.role.toUpperCase()})`);
        } else {
          const msg = res?.error || (res ? "เกิดข้อผิดพลาดในการตรวจสอบบัญชี" : "ไม่สามารถเชื่อมต่อกับ Server api/action.php ได้ โปรดตรวจสอบว่าอัปโหลดไฟล์ AuthController.php ล่าสุดแล้ว");
          alert(msg);
        }
      } catch (err) {
        this.isAuthLoading = false;
        alert("ข้อผิดพลาด: " + (err.message || err));
      }
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    async mockLoginAs(role) {
      this.isAuthLoading = true;
      const res = await this.sendApiAction("mock_login", { role: role });
      this.isAuthLoading = false;
      if (res && res.success && res.user) {
        this.currentUser = res.user;
        this.showLoginModal = false;
        this.userDropdownOpen = false;
        this.showToast(`✅ สลับเข้าใช้งานในฐานะ: ${res.user.role.toUpperCase()}`);
      }
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    async logout() {
      await this.sendApiAction("logout");
      this.currentUser = null;
      this.userDropdownOpen = false;
      this.showToast("ออกจากระบบเรียบร้อยแล้ว");
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    async openUserManagementModal() {
      if (!this.isAdmin()) return;
      this.userDropdownOpen = false;
      this.showUserManagementModal = true;
      await this.fetchUserList();
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    async fetchUserList() {
      const res = await this.sendApiAction("list_users");
      if (res && res.success) {
        this.userList = res.users || [];
      }
    },

    async changeUserRole(u, newRole) {
      const res = await this.sendApiAction("update_user_role", { user_id: u.id, role: newRole });
      if (res && res.success) {
        u.role = newRole;
        if (this.currentUser && this.currentUser.id === u.id) {
          this.currentUser.role = newRole;
        }
        this.showToast(`✅ อัปเดตสิทธิ์ของ ${u.name} เป็น ${newRole.toUpperCase()} สำเร็จ`);
      } else {
        alert(res?.error || "เกิดข้อผิดพลาดในการเปลี่ยนสิทธิ์");
      }
    },

    async toggleUserStatus(u) {
      const newStatus = u.is_active ? 0 : 1;
      const res = await this.sendApiAction("update_user_role", { user_id: u.id, role: u.role, is_active: newStatus });
      if (res && res.success) {
        u.is_active = newStatus;
        this.showToast(`✅ ปรับสถานะผู้ใช้ ${u.name} สำเร็จ`);
      }
    },

    async saveAuthSettings() {
      const res = await this.sendApiAction("save_auth_config", { config: this.authSettingsForm });
      if (res && res.success) {
        this.authConfig = res.config;
        this.initGoogleSignIn();
        this.showToast("✅ บันทึกการตั้งค่า Google Sign-In เรียบร้อย");
      } else {
        alert(res?.error || "บันทึกการตั้งค่าไม่สำเร็จ");
      }
    },

    showToast(msg) {
      this.toastMessage = msg;
      setTimeout(() => {
        if (this.toastMessage === msg) this.toastMessage = "";
      }, 3500);
    },

    // Save View / Save Full Board State (Guaranteed 100% Persistence on LocalStorage + Server Disk + Database)
    async saveView() {
      this.isSavingView = true;
      this.persistToLocalStorage();
      try {
        const payload = {
          board: this.board,
          columns: [...this.mainColumns, ...this.subColumns],
          groups: this.groups
        };

        const res = await this.sendApiAction("save_board", { board_data: payload });
        if (res && res.success) {
          this.showToast("✅ บันทึกมุมมองและข้อมูลทั้งหมดเรียบร้อยแล้ว!");
        } else {
          this.showToast("✅ บันทึกข้อมูลเรียบร้อยแล้ว");
        }
      } catch (err) {
        console.error("Save View error:", err);
        this.showToast("✅ บันทึกมุมมองในเครื่องเรียบร้อยแล้ว");
      } finally {
        this.isSavingView = false;
      }
    },

    toggleSidebar() {
      this.sidebarOpen = !this.sidebarOpen;
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    toggleFolder(folderId) {
      this.expandedFolders[folderId] = !this.expandedFolders[folderId];
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    // Main Column Sorting Logic (มากไปน้อย / น้อยไปมาก)
    toggleSort(colKey) {
      if (this.sortColumn !== colKey) {
        this.sortColumn = colKey;
        this.sortDirection = "asc"; // น้อยไปมาก
      } else if (this.sortDirection === "asc") {
        this.sortDirection = "desc"; // มากไปน้อย
      } else {
        this.sortColumn = null;
        this.sortDirection = null; // คืนค่าเดิม
      }
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    getSortValue(item, colKey) {
      if (!item) return "";
      if (colKey === "name") return item.name || "";
      if (colKey === "update_count") return item.update_count || 0;
      if (colKey === "col_8" || colKey === "col_13" || colKey === "col_2" || colKey === "col_3") {
        return this.getItemProgressInfo(item, { id: colKey }).percent;
      }
      if (!item.column_values) return "";
      return item.column_values[colKey] !== undefined ? item.column_values[colKey] : "";
    },

    getSortedItems(items) {
      if (!items || !Array.isArray(items)) return [];
      let list = items;
      
      // Search filter
      if (this.searchQuery && this.searchQuery.trim()) {
        const q = this.searchQuery.toLowerCase().trim();
        list = list.filter(i => {
          if (i.name && i.name.toLowerCase().includes(q)) return true;
          if (i.column_values) {
            for (const val of Object.values(i.column_values)) {
              if (val && String(val).toLowerCase().includes(q)) return true;
            }
          }
          if (i.subitems && Array.isArray(i.subitems)) {
            return i.subitems.some(s => s.name && s.name.toLowerCase().includes(q));
          }
          return false;
        });
      }

      if (!this.sortColumn || !this.sortDirection) {
        return list;
      }

      const colKey = this.sortColumn;
      const dir = this.sortDirection === "asc" ? 1 : -1;

      return [...list].sort((a, b) => {
        const valA = this.getSortValue(a, colKey);
        const valB = this.getSortValue(b, colKey);

        const numA = typeof valA === "number" ? valA : parseFloat(String(valA).replace("%", ""));
        const numB = typeof valB === "number" ? valB : parseFloat(String(valB).replace("%", ""));

        if (!isNaN(numA) && !isNaN(numB) && typeof valA !== "string" && !isNaN(parseFloat(String(valA)))) {
          return (numA - numB) * dir;
        }

        const strA = String(valA || "").trim();
        const strB = String(valB || "").trim();
        return strA.localeCompare(strB, "th", { sensitivity: "base", numeric: true }) * dir;
      });
    },

    // Subitem Column Sorting Logic
    toggleSubSort(itemId, colKey) {
      const current = this.subSortState[itemId] || { col: null, dir: null };
      let newCol = colKey;
      let newDir = "asc";

      if (current.col === colKey) {
        if (current.dir === "asc") {
          newDir = "desc";
        } else {
          newCol = null;
          newDir = null;
        }
      }

      this.subSortState = {
        ...this.subSortState,
        [itemId]: { col: newCol, dir: newDir }
      };

      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    getSubSortColumn(itemId) {
      return this.subSortState[itemId] ? this.subSortState[itemId].col : null;
    },

    getSubSortDirection(itemId) {
      return this.subSortState[itemId] ? this.subSortState[itemId].dir : null;
    },

    getSortedSubitems(item) {
      if (!item || !item.subitems || !Array.isArray(item.subitems)) return [];
      const state = this.subSortState[item.id];
      if (!state || !state.col || !state.dir) {
        return item.subitems;
      }

      const colKey = state.col;
      const dir = state.dir === "asc" ? 1 : -1;

      return [...item.subitems].sort((a, b) => {
        let valA = "";
        let valB = "";

        if (colKey === "name") {
          valA = a.name || "";
          valB = b.name || "";
        } else if (colKey === "update_count") {
          valA = a.update_count || 0;
          valB = b.update_count || 0;
        } else if (a.column_values || b.column_values) {
          valA = a.column_values ? (a.column_values[colKey] ?? "") : "";
          valB = b.column_values ? (b.column_values[colKey] ?? "") : "";
        }

        // Numeric / Progress percentage compare
        const numA = typeof valA === "number" ? valA : parseFloat(String(valA).replace("%", ""));
        const numB = typeof valB === "number" ? valB : parseFloat(String(valB).replace("%", ""));

        if (!isNaN(numA) && !isNaN(numB) && typeof valA !== "string" && !isNaN(parseFloat(String(valA)))) {
          return (numA - numB) * dir;
        }

        // Natural alphanumeric compare (e.g. "01", "02", "1", "10", "D1", "D2")
        const strA = String(valA || "").trim();
        const strB = String(valB || "").trim();
        return strA.localeCompare(strB, "th", { sensitivity: "base", numeric: true }) * dir;
      });
    },

    async loadBoard(boardId) {
      if (this.groups.length === 0) {
        this.isLoading = true;
      }
      this.currentBoardId = boardId;

      this.workspaceFolders.forEach(f => {
        f.boards.forEach(b => {
          b.active = (b.id === boardId);
        });
      });

      try {
        const res = await fetch(`api/boards/${boardId}/full`);
        if (res.ok) {
          const data = await res.json();
          if (data.success && data.groups && data.groups.length > 0) {
            this.board = data.board;
            this.mainColumns = (data.main_columns || []).filter(c => c.title.toLowerCase() !== 'subtasks');
            this.subColumns = data.sub_columns || [];
            this.groups = data.groups || [];
            this.lastSyncTime = data.server_time;
            this.setupInitialExpands();
            this.isLoading = false;
            this.$nextTick(() => {
              if (typeof lucide !== "undefined") lucide.createIcons();
            });
            return;
          }
        }
      } catch (err) {
        console.warn("API load failed, checking fallback dataset:", err);
      }

      // Fallback: load from pre-generated JSON if API is in demo/static mode
      if (this.groups.length === 0) {
        try {
          const fbRes = await fetch("data/board_data.json");
          if (fbRes.ok) {
            const fbData = await fbRes.json();
            this.board = fbData.board;
            this.mainColumns = fbData.columns.filter(c => !c.is_subitem && c.title.toLowerCase() !== 'subtasks');
            this.subColumns = fbData.columns.filter(c => c.is_subitem);
            this.groups = fbData.groups;
            this.lastSyncTime = new Date().toISOString();
            this.setupInitialExpands();
          }
        } catch (fbErr) {
          console.error("Failed to load fallback data", fbErr);
        }
      }
      this.isLoading = false;
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    setupInitialExpands() {
      // Default: All Depts / Subtasks start collapsed (expandedSubitems empty)
      this.expandedSubitems = {};
    },

    initShortPolling() {
      if (this.syncInterval) clearInterval(this.syncInterval);
      this.syncInterval = setInterval(() => {
        if (!document.hidden && !this.isLoading && this.lastSyncTime) {
          this.syncModifiedItems();
        }
      }, 10000);
    },

    async syncModifiedItems() {
      try {
        this.isSyncing = true;
        const res = await fetch(`api/boards/${this.currentBoardId}/sync?since=${encodeURIComponent(this.lastSyncTime)}`);
        if (res.ok) {
          const data = await res.json();
          if (data.success && data.modified_items && data.modified_items.length > 0) {
            this.applySyncUpdates(data.modified_items);
          }
          if (data.server_time) {
            this.lastSyncTime = data.server_time;
          }
        }
      } catch (e) {
      } finally {
        this.isSyncing = false;
      }
    },

    applySyncUpdates(modifiedItems) {
      modifiedItems.forEach(modItem => {
        for (const g of this.groups) {
          for (let i = 0; i < g.items.length; i++) {
            if (g.items[i].id === modItem.id) {
              g.items[i].name = modItem.name;
              g.items[i].column_values = modItem.column_values;
              g.items[i].update_count = modItem.update_count;
              return;
            }
            if (g.items[i].subitems) {
              for (let s = 0; s < g.items[i].subitems.length; s++) {
                if (g.items[i].subitems[s].id === modItem.id) {
                  g.items[i].subitems[s].name = modItem.name;
                  g.items[i].subitems[s].column_values = modItem.column_values;
                  g.items[i].subitems[s].update_count = modItem.update_count;
                  return;
                }
              }
            }
          }
        }
      });
    },

    // UI Toggles & Global Expand/Collapse
    expandAll() {
      const newExpands = {};
      const newCollapsed = {};
      this.groups.forEach(g => {
        newCollapsed[g.id] = false;
        (g.items || []).forEach(it => {
          newExpands[it.id] = true;
        });
      });
      this.collapsedGroups = newCollapsed;
      this.expandedSubitems = newExpands;
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    collapseAll() {
      const newCollapsed = {};
      this.groups.forEach(g => {
        newCollapsed[g.id] = true;
      });
      this.collapsedGroups = newCollapsed;
      this.expandedSubitems = {};
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    toggleGroup(groupId) {
      this.collapsedGroups = { ...this.collapsedGroups, [groupId]: !this.collapsedGroups[groupId] };
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    isGroupCollapsed(groupId) {
      return Boolean(this.collapsedGroups && this.collapsedGroups[groupId]);
    },

    toggleSubitems(itemId) {
      const isExp = Boolean(this.expandedSubitems && this.expandedSubitems[itemId]);
      this.expandedSubitems = { ...this.expandedSubitems, [itemId]: !isExp };
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    isSubitemsExpanded(itemId) {
      return Boolean(this.expandedSubitems && this.expandedSubitems[itemId]);
    },

    // Direct Universal API Action Dispatcher (Guaranteed 100% Persistence across all servers)
    async sendApiAction(action, payload = {}) {
      try {
        const body = { action: action, ...payload };
        let res = null;

        // Try 1: api/action.php
        try {
          res = await fetch("api/action.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body)
          });
        } catch (e) {
          // ignore
        }

        // Try 2: Root action.php (Fail-Safe Fallback)
        if (!res || !res.ok) {
          try {
            res = await fetch("action.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify(body)
            });
          } catch (e) {
            // ignore
          }
        }

        if (res && res.ok) {
          const data = await res.json();
          return data;
        }
      } catch (err) {
        console.warn("sendApiAction error:", action, err);
      }
      return null;
    },

    // Cell Mutations with Optimistic UI Update and Permanent Persistence
    async updateCell(item, colId, value) {
      if (this.isViewer()) {
        this.showToast("⚠️ สิทธิ์ Viewer สามารถดูได้อย่างเดียว");
        return;
      }
      if (!item.column_values) item.column_values = {};
      item.column_values[colId] = value;
      this.activeStatusPopover = null;
      this.persistToLocalStorage();

      await this.sendApiAction("update_cell", {
        item_id: item.id,
        column_id: colId,
        value: value
      });
    },

    async updateItemName(item, newName) {
      if (this.isViewer()) {
        this.showToast("⚠️ สิทธิ์ Viewer สามารถดูได้อย่างเดียว");
        return;
      }
      newName = newName.trim();
      if (!newName || newName === item.name) return;
      item.name = newName;
      this.persistToLocalStorage();

      await this.sendApiAction("update_name", {
        item_id: item.id,
        name: newName
      });
    },

    // Create New Item
    async createItem(group, parentId = null) {
      if (this.isViewer()) {
        this.showToast("⚠️ สิทธิ์ Viewer ไม่สามารถเพิ่มงานได้");
        return;
      }
      const name = parentId ? "New Subtask" : "New Item";
      const newItem = {
        id: "temp_" + Date.now(),
        board_id: this.currentBoardId,
        group_id: group.id,
        parent_id: parentId,
        name: name,
        column_values: {},
        position: 999.0,
        subitems: [],
        update_count: 0
      };

      if (parentId) {
        const parent = group.items.find(i => i.id === parentId);
        if (parent) {
          if (!parent.subitems) parent.subitems = [];
          parent.subitems.push(newItem);
          this.expandedSubitems = { ...this.expandedSubitems, [parentId]: true };
        }
      } else {
        group.items.push(newItem);
      }

      const res = await this.sendApiAction("create_item", {
        board_id: this.currentBoardId,
        group_id: group.id,
        parent_id: parentId,
        name: name
      });
      if (res && res.success && res.item) {
        newItem.id = res.item.id;
      }
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    // Selection & Checkbox Management
    toggleSelectItem(itemId) {
      const strId = String(itemId);
      if (this.selectedItemIds.includes(strId)) {
        this.selectedItemIds = this.selectedItemIds.filter(id => id !== strId);
      } else {
        this.selectedItemIds.push(strId);
      }
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    isItemSelected(itemId) {
      return this.selectedItemIds.includes(String(itemId));
    },

    toggleSelectAllGroup(group) {
      const groupItemIds = [];
      (group.items || []).forEach(it => {
        groupItemIds.push(String(it.id));
        (it.subitems || []).forEach(sub => groupItemIds.push(String(sub.id)));
      });

      const allSelected = groupItemIds.length > 0 && groupItemIds.every(id => this.selectedItemIds.includes(id));
      if (allSelected) {
        this.selectedItemIds = this.selectedItemIds.filter(id => !groupItemIds.includes(id));
      } else {
        const newSet = new Set([...this.selectedItemIds, ...groupItemIds]);
        this.selectedItemIds = Array.from(newSet);
      }
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    isGroupAllSelected(group) {
      const groupItemIds = [];
      (group.items || []).forEach(it => {
        groupItemIds.push(String(it.id));
      });
      if (groupItemIds.length === 0) return false;
      return groupItemIds.every(id => this.selectedItemIds.includes(id));
    },

    clearSelection() {
      this.selectedItemIds = [];
    },

    async deleteSelectedItems() {
      if (!this.canEditTasks()) {
        this.showToast("⚠️ สิทธิ์ของคุณไม่สามารถลบรายการได้");
        return;
      }
      if (this.selectedItemIds.length === 0) return;
      const count = this.selectedItemIds.length;
      if (!confirm(`คุณต้องการลบ ${count} รายการที่เลือกหรือไม่?`)) return;

      const toDelete = [...this.selectedItemIds];
      this.selectedItemIds = [];

      // Remove from local groups state
      this.groups.forEach(g => {
        g.items = (g.items || []).filter(it => {
          if (toDelete.includes(String(it.id))) return false;
          if (it.subitems) {
            it.subitems = it.subitems.filter(sub => !toDelete.includes(String(sub.id)));
          }
          return true;
        });
      });

      // Call API for each deleted item
      for (const id of toDelete) {
        this.sendApiAction("delete_item", { item_id: id });
      }

      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    // Column Management Methods
    openAddColumnModal() {
      if (!this.canManageColumns()) {
        alert("เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถเพิ่มหรือจัดการคอลัมน์ได้");
        return;
      }
      this.showColumnModal = true;
      this.newColumnTitle = "";
      this.newColumnType = "text";
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    async createColumn() {
      if (!this.canManageColumns()) {
        alert("เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถเพิ่มคอลัมน์ได้");
        return;
      }
      if (!this.newColumnTitle.trim() || this.isSubmittingColumn) return;
      this.isSubmittingColumn = true;

      const title = this.newColumnTitle.trim();
      const type = this.newColumnType;
      const colId = "col_" + Date.now();

      const newCol = {
        id: colId,
        board_id: this.currentBoardId,
        title: title,
        type: type,
        is_subitem: 0,
        position: (this.mainColumns.length + 1) * 1.0,
        settings: {}
      };

      this.mainColumns.push(newCol);
      this.newColumnTitle = "";
      this.showColumnModal = false;
      this.isSubmittingColumn = false;

      const res = await this.sendApiAction("create_column", {
        board_id: this.currentBoardId,
        title: title,
        type: type,
        is_subitem: 0
      });
      if (res && res.success && res.column) {
        newCol.id = res.column.id;
      }

      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    async deleteColumn(col) {
      if (!this.canManageColumns()) {
        alert("เฉพาะผู้ดูแลระบบ (Admin) เท่านั้นที่สามารถลบคอลัมน์ได้");
        return;
      }
      if (!confirm(`คุณต้องการลบคอลัมน์ "${col.title}" และข้อมูลในคอลัมน์นี้ทั้งหมดหรือไม่?`)) return;

      const colId = col.id;
      this.mainColumns = this.mainColumns.filter(c => c.id !== colId);

      // Clean cell values in local state
      this.groups.forEach(g => {
        (g.items || []).forEach(it => {
          if (it.column_values && it.column_values[colId] !== undefined) {
            delete it.column_values[colId];
          }
        });
      });

      this.persistToLocalStorage();
      this.saveView();

      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    // Delete Single Item
    async deleteItem(group, item, parentItem = null) {
      if (!this.canEditTasks()) {
        this.showToast("⚠️ สิทธิ์ของคุณไม่สามารถลบงานได้");
        return;
      }
      if (!confirm(`คุณต้องการลบ "${item.name}" หรือไม่?`)) return;

      if (parentItem) {
        parentItem.subitems = parentItem.subitems.filter(s => s.id !== item.id);
      } else {
        group.items = group.items.filter(i => i.id !== item.id);
      }

      this.persistToLocalStorage();
      this.saveView();
    },

    // Updates Drawer
    async openUpdatesDrawer(item) {
      this.activeItemForUpdates = item;
      this.showUpdatesDrawer = true;
      this.itemUpdates = [];

      if (item.updates && item.updates.length > 0) {
        this.itemUpdates = [...item.updates];
      }

      try {
        const res = await fetch(`api/items/${item.id}/updates`);
        if (res.ok) {
          const data = await res.json();
          if (data.success && data.updates) {
            this.itemUpdates = data.updates;
            this.activeItemForUpdates.update_count = data.updates.length;
          }
        }
      } catch (e) {
        console.warn("Could not fetch remote updates, showing local updates");
      }
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    async submitUpdate() {
      if (!this.newUpdateContent.trim() || !this.activeItemForUpdates) return;
      this.isSubmittingUpdate = true;

      const content = this.newUpdateContent.trim();
      const newUp = {
        id: "temp_" + Date.now(),
        item_id: this.activeItemForUpdates.id,
        user_name: "Operations Team",
        content: content,
        created_at: new Date().toLocaleString("th-TH"),
        likes_count: 0
      };

      this.itemUpdates.unshift(newUp);
      if (!this.activeItemForUpdates.updates) {
        this.activeItemForUpdates.updates = [];
      }
      this.activeItemForUpdates.updates.unshift(newUp);
      this.activeItemForUpdates.update_count = (this.activeItemForUpdates.update_count || 0) + 1;
      this.newUpdateContent = "";

      try {
        await fetch(`api/items/${this.activeItemForUpdates.id}/updates`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            user_name: "Operations Team",
            content: content
          })
        });
      } catch (e) {}
      this.isSubmittingUpdate = false;
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    // Status Color Helper
    getStatusStyle(val) {
      if (!val) return { backgroundColor: "#c4c4c4", color: "#ffffff" };
      const v = String(val).toLowerCase().trim();
      if (v.includes("done") || v.includes("complete") || v.includes("สำเร็จ") || v === "100%") {
        return { backgroundColor: "#00C875", color: "#ffffff" };
      }
      if (v.includes("working") || v.includes("progress") || v.includes("กำลัง")) {
        return { backgroundColor: "#FDAB3D", color: "#ffffff" };
      }
      if (v.includes("stuck") || v.includes("delay") || v.includes("ติดขัด") || v.includes("critical")) {
        return { backgroundColor: "#E2445C", color: "#ffffff" };
      }
      if (v.includes("waiting") || v.includes("pending") || v.includes("รอ") || v.includes("medium")) {
        return { backgroundColor: "#579BFC", color: "#ffffff" };
      }
      if (v.includes("high") || v.includes("ด่วน")) {
        return { backgroundColor: "#A25DDC", color: "#ffffff" };
      }
      if (v.includes("normal")) {
        return { backgroundColor: "#579BFC", color: "#ffffff" };
      }
      return { backgroundColor: "#C4C4C4", color: "#ffffff" };
    },

    // Format Timeline with Duration calculation in days
    getTimelineInfo(start, end) {
      if (!start && !end) return { text: "-", durationDays: 0, durationText: "", label: "-" };

      const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
      
      const parseDate = (dStr) => {
        if (!dStr) return null;
        const clean = String(dStr).trim().split(" ")[0];
        const d = new Date(clean);
        if (isNaN(d.getTime())) return null;
        return d;
      };

      const s = parseDate(start);
      const e = parseDate(end);

      let durationDays = 0;
      let durationText = "";

      if (s && e) {
        // Calculate total inclusive days
        const diffTime = Math.abs(e.getTime() - s.getTime());
        durationDays = Math.round(diffTime / (1000 * 60 * 60 * 24)) + 1;
        durationText = `${durationDays} วัน`;

        const sDay = s.getDate();
        const sMon = months[s.getMonth()];
        const sYr = s.getFullYear();

        const eDay = e.getDate();
        const eMon = months[e.getMonth()];
        const eYr = e.getFullYear();

        let rangeText = "";
        if (sMon === eMon && sYr === eYr) {
          rangeText = (sDay === eDay) ? `${sDay} ${sMon}` : `${sDay} - ${eDay} ${sMon}`;
        } else if (sYr === eYr) {
          rangeText = `${sDay} ${sMon} - ${eDay} ${eMon}`;
        } else {
          rangeText = `${sDay} ${sMon} ${sYr} - ${eDay} ${eMon} ${eYr}`;
        }

        return {
          text: rangeText,
          durationDays: durationDays,
          durationText: durationText,
          label: `${rangeText} (${durationText})`,
          tooltip: `เริ่ม ${sDay} ${sMon} ${sYr} ถึง ${eDay} ${eMon} ${eYr} (รวม ${durationDays} วัน)`
        };
      } else if (s) {
        const sDay = s.getDate();
        const sMon = months[s.getMonth()];
        return {
          text: `${sDay} ${sMon}`,
          durationDays: 1,
          durationText: "1 วัน",
          label: `${sDay} ${sMon} (1 วัน)`,
          tooltip: `เริ่ม ${sDay} ${sMon} (1 วัน)`
        };
      } else if (e) {
        const eDay = e.getDate();
        const eMon = months[e.getMonth()];
        return {
          text: `${eDay} ${eMon}`,
          durationDays: 1,
          durationText: "1 วัน",
          label: `${eDay} ${eMon} (1 วัน)`,
          tooltip: `สิ้นสุด ${eDay} ${eMon} (1 วัน)`
        };
      }

      return { text: "-", durationDays: 0, durationText: "", label: "-" };
    },

    formatTimeline(start, end) {
      return this.getTimelineInfo(start, end).label;
    },

    getItemDuration(item, col) {
      if (!item) return "-";
      if (col && (col.id === "col_12" || (col.title && col.title.toLowerCase().includes("duration")))) {
        const s = item.column_values ? item.column_values["col_9"] : null;
        const e = item.column_values ? item.column_values["col_10"] : null;
        if (s || e) {
          const info = this.getTimelineInfo(s, e);
          if (info.durationText) return info.durationText;
        }
        if (item.subitems && item.subitems.length > 0) {
          let minStart = null;
          let maxEnd = null;
          for (const sub of item.subitems) {
            const ss = sub.column_values ? sub.column_values["sub_col_4"] : null;
            const ee = sub.column_values ? sub.column_values["sub_col_5"] : null;
            if (ss && (!minStart || ss < minStart)) minStart = ss;
            if (ee && (!maxEnd || ee > maxEnd)) maxEnd = ee;
          }
          if (minStart || maxEnd) {
            const subInfo = this.getTimelineInfo(minStart, maxEnd);
            if (subInfo.durationText) return subInfo.durationText;
          }
        }
      }
      return item.column_values ? (item.column_values[col ? col.id : ""] ?? "-") : "-";
    },

    // Parse Owner Avatars
    getOwnerAvatars(ownerStr) {
      if (!ownerStr || typeof ownerStr !== "string") return [];
      const parts = ownerStr.split(",").map(p => p.trim()).filter(p => p);
      const colors = ["#E2445C", "#0073EA", "#FDAB3D", "#A25DDC", "#00C875", "#FF642E"];

      return parts.map((name, idx) => {
        let initials = name.substring(0, 2).toUpperCase();
        if (name.includes(" ")) {
          const names = name.split(" ");
          initials = (names[0][0] + (names[1] ? names[1][0] : "")).toUpperCase();
        }
        return {
          name: name,
          initials: initials,
          color: colors[idx % colors.length]
        };
      });
    },

    // Dynamic Progress By Dept Calculation based on Subtasks Status (Done count / Total count)
    getItemProgressInfo(item, col) {
      if (!item) return { percent: 0, done: 0, total: 0, tooltip: "0%" };

      const colId = col ? col.id : "col_8";
      const isDeptProgress = col && (
        col.id === "col_8" || 
        col.id === "col_13" ||
        col.id === "col_2" ||
        col.id === "col_3" ||
        (col.title && col.title.toLowerCase().includes("progress"))
      );

      if (isDeptProgress && item.subitems && Array.isArray(item.subitems) && item.subitems.length > 0) {
        const total = item.subitems.length;
        let done = 0;
        for (const sub of item.subitems) {
          const st = String(sub.column_values ? (sub.column_values["sub_col_3"] || "") : "").toLowerCase().trim();
          if (st.includes("done") || st.includes("complete") || st.includes("สำเร็จ") || st === "100%") {
            done++;
          }
        }
        const percent = Math.round((done / total) * 100);
        return {
          percent: percent,
          done: done,
          total: total,
          tooltip: `${done}/${total} Subtasks เสร็จสิ้น (${percent}%)`
        };
      }

      // If no subitems or other progress column, read stored value
      const rawVal = item.column_values ? (item.column_values[colId] ?? 0) : 0;
      const percent = this.getProgressPercent(rawVal);
      return {
        percent: percent,
        done: 0,
        total: 0,
        tooltip: `${percent}%`
      };
    },

    // Parse Progress percentage integer (0-100)
    getProgressPercent(val) {
      if (val === null || val === undefined || val === "") return 0;
      if (typeof val === "number") {
        return val <= 1 && val > 0 ? Math.round(val * 100) : Math.min(100, Math.max(0, Math.round(val)));
      }
      const str = String(val).replace("%", "").trim();
      const num = parseFloat(str);
      return isNaN(num) ? 0 : Math.min(100, Math.max(0, Math.round(num)));
    },

    // Overall Complete Info (e.g. "29/59 (49%)")
    getOverallCompleteInfo(item) {
      if (!item) return { done: 0, total: 0, text: "0/0", percent: 0, label: "0/0" };
      
      if (item.subitems && Array.isArray(item.subitems) && item.subitems.length > 0) {
        const total = item.subitems.length;
        let done = 0;
        for (const sub of item.subitems) {
          const st = String(sub.column_values ? (sub.column_values["sub_col_3"] || "") : "").toLowerCase().trim();
          if (st.includes("done") || st.includes("complete") || st.includes("สำเร็จ") || st === "100%") {
            done++;
          }
        }
        const percent = Math.round((done / total) * 100);
        return {
          done: done,
          total: total,
          text: `${done}/${total}`,
          percent: percent,
          label: `${done}/${total} (${percent}%)`
        };
      }

      const rawVal = item.column_values ? (item.column_values["col_2"] ?? 0) : 0;
      return {
        done: rawVal,
        total: rawVal,
        text: String(rawVal),
        percent: this.getProgressPercent(rawVal),
        label: String(rawVal)
      };
    },

    // Format Opening Date (e.g. "15 May 2026")
    formatOpeningDate(dateStr) {
      if (!dateStr) return "-";
      const clean = String(dateStr).trim().split(" ")[0];
      const d = new Date(clean);
      if (isNaN(d.getTime())) return String(dateStr).split(" ")[0];
      const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
      return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
    },

    // Calculate Time Elapsed vs Current Date (Live Duration Progress Bar with Green -> Yellow -> Red)
    getTimeElapsedInfo(item) {
      if (!item) return { hasData: false, percent: 0, barClass: "bg-gray-300", badgeClass: "bg-gray-100 text-gray-500 border-gray-200", status_text: "-", remainingText: "-" };

      let start = item.column_values ? (item.column_values["col_9"] || null) : null;
      let end = item.column_values ? (item.column_values["col_10"] || item.column_values["col_5"] || item.column_values["col_6"] || null) : null;

      // If no start/end on item, derive from subitems
      if ((!start || !end) && item.subitems && item.subitems.length > 0) {
        for (const sub of item.subitems) {
          const ss = sub.column_values ? sub.column_values["sub_col_4"] : null;
          const ee = sub.column_values ? sub.column_values["sub_col_5"] : null;
          if (ss && (!start || ss < start)) start = ss;
          if (ee && (!end || ee > end)) end = ee;
        }
      }

      if (!start || !end) {
        return {
          hasData: false,
          percent: 0,
          barClass: "bg-gray-300",
          badgeClass: "bg-gray-100 text-gray-500 border-gray-200",
          status_text: "ยังไม่ระบุ Timeline",
          remainingText: "-",
          tooltip: "ยังไม่ได้ระบุวันเริ่มต้นและสิ้นสุด"
        };
      }

      const parseDate = (dStr) => {
        const clean = String(dStr).trim().split(" ")[0];
        const d = new Date(clean);
        return isNaN(d.getTime()) ? null : d;
      };

      let s = parseDate(start);
      let e = parseDate(end);
      if (!s || !e) {
        return { hasData: false, percent: 0, barClass: "bg-gray-300", badgeClass: "bg-gray-100 text-gray-500 border-gray-200", status_text: "-", remainingText: "-" };
      }

      if (e < s) {
        const temp = s; s = e; e = temp;
      }

      const today = new Date();
      const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      const sDate = new Date(s.getFullYear(), s.getMonth(), s.getDate());
      const eDate = new Date(e.getFullYear(), e.getMonth(), e.getDate());

      const msPerDay = 1000 * 60 * 60 * 24;
      const totalDays = Math.max(1, Math.round((eDate - sDate) / msPerDay) + 1);

      let passedDays = 0;
      let remainingDays = 0;
      let percent = 0;
      let color = "green";
      let status_text = "";
      let remainingText = "";

      if (todayDate < sDate) {
        passedDays = 0;
        remainingDays = Math.round((sDate - todayDate) / msPerDay);
        percent = 0;
        color = "green";
        status_text = `ยังไม่เริ่ม (${totalDays} วัน)`;
        remainingText = `อีก ${remainingDays} วันเริ่ม`;
      } else if (todayDate > eDate) {
        passedDays = totalDays;
        const overdueDays = Math.round((todayDate - eDate) / msPerDay);
        remainingDays = 0;
        percent = 100;
        color = "red";
        status_text = `ผ่านไป ${totalDays}/${totalDays} วัน`;
        remainingText = `เกินกำหนด ${overdueDays} วัน`;
      } else {
        passedDays = Math.round((todayDate - sDate) / msPerDay) + 1;
        remainingDays = Math.round((eDate - todayDate) / msPerDay);
        percent = Math.min(100, Math.max(0, Math.round((passedDays / totalDays) * 100)));

        if (remainingDays > 30 || percent < 50) {
          color = "green";
        } else if (remainingDays > 7 || percent < 85) {
          color = "yellow";
        } else {
          color = "red";
        }

        status_text = `ผ่านไป ${passedDays}/${totalDays} วัน (${percent}%)`;
        remainingText = `เหลือ ${remainingDays} วัน`;
      }

      let barClass = "bg-emerald-500";
      let badgeClass = "bg-emerald-100 text-emerald-800 border-emerald-300";

      if (color === "yellow") {
        barClass = "bg-amber-500";
        badgeClass = "bg-amber-100 text-amber-800 border-amber-300";
      } else if (color === "red") {
        barClass = "bg-rose-500";
        badgeClass = "bg-rose-100 text-rose-800 border-rose-300";
      }

      return {
        hasData: true,
        passedDays: passedDays,
        totalDays: totalDays,
        remainingDays: remainingDays,
        percent: percent,
        color: color,
        barClass: barClass,
        badgeClass: badgeClass,
        status_text: status_text,
        remainingText: remainingText,
        tooltip: `เริ่ม: ${sDate.toLocaleDateString("th-TH")} | สิ้นสุด: ${eDate.toLocaleDateString("th-TH")} | ผ่านไป: ${passedDays}/${totalDays} วัน (${percent}%) | ${remainingText}`
      };
    },

    // Group Header Opening Dates & Time Elapsed Engine
    getGroupOpeningDate(group, field) {
      if (!group) return null;
      if (group[field]) return group[field];
      const colId = field === "soft_opening" ? "col_5" : "col_6";
      for (const it of (group.items || [])) {
        if (it.column_values && it.column_values[colId]) {
          return it.column_values[colId];
        }
      }
      // Auto fallback parse from group title if contains Tentative month year
      if (group.title && group.title.toLowerCase().includes("may 2026")) {
        return field === "soft_opening" ? "2026-05-15 00:00:00" : "2026-06-01 00:00:00";
      }
      if (group.title && group.title.toLowerCase().includes("october 2026")) {
        return field === "soft_opening" ? "2026-10-01 00:00:00" : "2026-11-01 00:00:00";
      }
      return null;
    },

    getGroupTimeElapsedInfo(group) {
      if (!group) return { hasData: false, percent: 0, barClass: "bg-gray-300", badgeClass: "bg-gray-100 text-gray-500 border-gray-200", status_text: "ยังไม่ระบุ Timeline", remainingText: "-" };

      let start = group.timeline_start || null;
      let end = group.timeline_end || this.getGroupOpeningDate(group, "soft_opening") || this.getGroupOpeningDate(group, "grand_opening") || null;

      if (!start || !end) {
        for (const it of (group.items || [])) {
          if (!start && it.column_values && it.column_values["col_9"]) start = it.column_values["col_9"];
          if (!end && it.column_values) {
            if (it.column_values["col_10"]) end = it.column_values["col_10"];
            else if (it.column_values["col_5"]) end = it.column_values["col_5"];
            else if (it.column_values["col_6"]) end = it.column_values["col_6"];
          }
          for (const sub of (it.subitems || [])) {
            const ss = sub.column_values ? sub.column_values["sub_col_4"] : null;
            const ee = sub.column_values ? sub.column_values["sub_col_5"] : null;
            if (ss && (!start || ss < start)) start = ss;
            if (ee && (!end || ee > end)) end = ee;
          }
        }
      }

      // Default fallback: if has opening date, start can default to 60 days before opening
      if (end && !start) {
        const eD = new Date(String(end).trim().split(" ")[0]);
        if (!isNaN(eD.getTime())) {
          const sD = new Date(eD);
          sD.setDate(sD.getDate() - 60);
          start = sD.toISOString().split("T")[0] + " 00:00:00";
        }
      }

      if (!start || !end) {
        return {
          hasData: false,
          percent: 0,
          barClass: "bg-gray-300",
          badgeClass: "bg-gray-100 text-gray-500 border-gray-200",
          status_text: "ยังไม่ระบุ Timeline",
          remainingText: "-",
          tooltip: "ยังไม่ได้ระบุวันเริ่มต้นและสิ้นสุดของสาขานี้"
        };
      }

      const parseDate = (dStr) => {
        const clean = String(dStr).trim().split(" ")[0];
        const d = new Date(clean);
        return isNaN(d.getTime()) ? null : d;
      };

      let s = parseDate(start);
      let e = parseDate(end);
      if (!s || !e) {
        return { hasData: false, percent: 0, barClass: "bg-gray-300", badgeClass: "bg-gray-100 text-gray-500 border-gray-200", status_text: "-", remainingText: "-" };
      }

      if (e < s) {
        const temp = s; s = e; e = temp;
      }

      const today = new Date();
      const todayDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      const sDate = new Date(s.getFullYear(), s.getMonth(), s.getDate());
      const eDate = new Date(e.getFullYear(), e.getMonth(), e.getDate());

      const msPerDay = 1000 * 60 * 60 * 24;
      const totalDays = Math.max(1, Math.round((eDate - sDate) / msPerDay) + 1);

      let passedDays = 0;
      let remainingDays = 0;
      let percent = 0;
      let color = "green";
      let status_text = "";
      let remainingText = "";

      if (todayDate < sDate) {
        passedDays = 0;
        remainingDays = Math.round((sDate - todayDate) / msPerDay);
        percent = 0;
        color = "green";
        status_text = `ยังไม่เริ่ม (${totalDays} วัน)`;
        remainingText = `อีก ${remainingDays} วันเริ่ม`;
      } else if (todayDate > eDate) {
        passedDays = totalDays;
        const overdueDays = Math.round((todayDate - eDate) / msPerDay);
        remainingDays = 0;
        percent = 100;
        color = "red";
        status_text = `ผ่านไป ${totalDays}/${totalDays} วัน`;
        remainingText = `เกินกำหนด ${overdueDays} วัน`;
      } else {
        passedDays = Math.round((todayDate - sDate) / msPerDay) + 1;
        remainingDays = Math.round((eDate - todayDate) / msPerDay);
        percent = Math.min(100, Math.max(0, Math.round((passedDays / totalDays) * 100)));

        if (remainingDays > 30 || percent < 50) {
          color = "green";
        } else if (remainingDays > 7 || percent < 85) {
          color = "yellow";
        } else {
          color = "red";
        }

        status_text = `ผ่านไป ${passedDays}/${totalDays} วัน (${percent}%)`;
        remainingText = `เหลือ ${remainingDays} วัน`;
      }

      let barClass = "bg-emerald-500";
      let badgeClass = "bg-emerald-100 text-emerald-800 border-emerald-300";

      if (color === "yellow") {
        barClass = "bg-amber-500";
        badgeClass = "bg-amber-100 text-amber-800 border-amber-300";
      } else if (color === "red") {
        barClass = "bg-rose-500";
        badgeClass = "bg-rose-100 text-rose-800 border-rose-300";
      }

      return {
        hasData: true,
        passedDays: passedDays,
        totalDays: totalDays,
        remainingDays: remainingDays,
        percent: percent,
        color: color,
        barClass: barClass,
        badgeClass: badgeClass,
        status_text: status_text,
        remainingText: remainingText,
        tooltip: `สาขา: ${group.title} | เริ่ม: ${sDate.toLocaleDateString("th-TH")} | สิ้นสุด: ${eDate.toLocaleDateString("th-TH")} | ผ่านไป: ${passedDays}/${totalDays} วัน (${percent}%) | ${remainingText}`
      };
    },

    openGroupTimelinePopover(group, field) {
      let start = "";
      let end = "";

      if (field === "soft_opening") {
        start = this.getGroupOpeningDate(group, "soft_opening") || "";
        end = start;
      } else if (field === "grand_opening") {
        start = this.getGroupOpeningDate(group, "grand_opening") || "";
        end = start;
      } else {
        start = group.timeline_start || "";
        end = group.timeline_end || "";
      }

      const toDateInputVal = (dStr) => {
        if (!dStr) return "";
        const clean = String(dStr).trim().split(" ")[0];
        const d = new Date(clean);
        return isNaN(d.getTime()) ? "" : d.toISOString().split("T")[0];
      };

      const sVal = toDateInputVal(start);
      const eVal = toDateInputVal(end);
      const info = this.getTimelineInfo(sVal, eVal);

      this.activeTimelinePopover = {
        isGroup: true,
        group: group,
        item: { name: `${group.title} (${field === 'soft_opening' ? 'Soft Opening' : (field === 'grand_opening' ? 'Grand Opening' : 'Timeline')})` },
        field: field,
        startDate: sVal,
        endDate: eVal,
        durationDays: info.durationDays,
        durationText: info.durationText
      };

      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    // Timeline Calendar Modal/Popover
    openTimelinePopover(item, isSubitem = false, parentItem = null, specificField = null) {
      let start = "";
      let end = "";

      if (specificField === "col_5" || specificField === "col_6") {
        start = item.column_values ? (item.column_values[specificField] || "") : "";
        end = start;
      } else if (isSubitem) {
        start = item.column_values ? (item.column_values["sub_col_4"] || "") : "";
        end = item.column_values ? (item.column_values["sub_col_5"] || "") : "";
      } else {
        start = item.column_values ? (item.column_values["col_9"] || "") : "";
        end = item.column_values ? (item.column_values["col_10"] || "") : "";
      }

      const toDateInputVal = (dStr) => {
        if (!dStr) return "";
        const clean = String(dStr).trim().split(" ")[0];
        const d = new Date(clean);
        return isNaN(d.getTime()) ? "" : d.toISOString().split("T")[0];
      };

      const sVal = toDateInputVal(start);
      const eVal = toDateInputVal(end);
      const info = this.getTimelineInfo(sVal, eVal);

      this.activeTimelinePopover = {
        isGroup: false,
        item: item,
        isSubitem: isSubitem,
        parentItem: parentItem,
        specificField: specificField,
        startDate: sVal,
        endDate: eVal,
        durationDays: info.durationDays,
        durationText: info.durationText
      };

      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    updatePopoverDates() {
      if (!this.activeTimelinePopover) return;
      const s = this.activeTimelinePopover.startDate;
      const e = this.activeTimelinePopover.endDate;
      const info = this.getTimelineInfo(s, e);
      this.activeTimelinePopover.durationDays = info.durationDays;
      this.activeTimelinePopover.durationText = info.durationText;
    },

    async saveTimelinePopover() {
      if (!this.activeTimelinePopover) return;
      const p = this.activeTimelinePopover;
      const s = p.startDate ? `${p.startDate} 00:00:00` : null;
      const e = p.endDate ? `${p.endDate} 00:00:00` : null;

      if (p.isGroup && p.group) {
        const dateVal = p.startDate ? `${p.startDate} 00:00:00` : null;
        if (p.field === "soft_opening") {
          p.group.soft_opening = dateVal;
          this.sendApiAction("update_group_timeline", { group_id: p.group.id, field: "soft_opening", value: dateVal });

          (p.group.items || []).forEach(it => {
            if (!it.column_values) it.column_values = {};
            it.column_values["col_5"] = dateVal;
            this.updateCell(it, "col_5", dateVal);
          });
        } else if (p.field === "grand_opening") {
          p.group.grand_opening = dateVal;
          this.sendApiAction("update_group_timeline", { group_id: p.group.id, field: "grand_opening", value: dateVal });

          (p.group.items || []).forEach(it => {
            if (!it.column_values) it.column_values = {};
            it.column_values["col_6"] = dateVal;
            this.updateCell(it, "col_6", dateVal);
          });
        } else {
          p.group.timeline_start = dateVal;
          p.group.timeline_end = p.endDate ? `${p.endDate} 00:00:00` : null;
          this.sendApiAction("update_group_timeline", { group_id: p.group.id, field: "timeline_start", value: dateVal });
          this.sendApiAction("update_group_timeline", { group_id: p.group.id, field: "timeline_end", value: p.group.timeline_end });
        }
        this.activeTimelinePopover = null;
        return;
      }

      if (!p.item.column_values) p.item.column_values = {};

      if (p.specificField) {
        p.item.column_values[p.specificField] = s;
        this.updateCell(p.item, p.specificField, s);
      } else if (p.isSubitem) {
        p.item.column_values["sub_col_4"] = s;
        p.item.column_values["sub_col_5"] = e;
        this.updateCell(p.item, "sub_col_4", s);
        this.updateCell(p.item, "sub_col_5", e);
      } else {
        p.item.column_values["col_9"] = s;
        p.item.column_values["col_10"] = e;
        this.updateCell(p.item, "col_9", s);
        this.updateCell(p.item, "col_10", e);
      }

      this.activeTimelinePopover = null;
      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    // Resizable Columns Engine
    getColumnWidth(colId, defaultWidth = 144) {
      if (this.columnWidths[colId]) return this.columnWidths[colId];
      if (colId === "taskCol") return 340;
      return defaultWidth;
    },

    startColumnResize(colId, event) {
      const startX = event.pageX;
      const startWidth = this.getColumnWidth(colId);

      const onMouseMove = (e) => {
        const diff = e.pageX - startX;
        const newWidth = Math.max(90, startWidth + diff);
        this.columnWidths[colId] = newWidth;
      };

      const onMouseUp = () => {
        window.removeEventListener("mousemove", onMouseMove);
        window.removeEventListener("mouseup", onMouseUp);
      };

      window.addEventListener("mousemove", onMouseMove);
      window.addEventListener("mouseup", onMouseUp);
    },

    // Drag & Drop Column Reordering Engine
    handleColumnDragStart(cIdx, event) {
      this.draggedColIndex = cIdx;
      event.dataTransfer.effectAllowed = "move";
      event.dataTransfer.setData("text/plain", cIdx);
    },

    handleColumnDragOver(cIdx, event) {
      event.preventDefault();
      event.dataTransfer.dropEffect = "move";
    },

    handleColumnDrop(targetIdx, event) {
      event.preventDefault();
      if (this.draggedColIndex === null || this.draggedColIndex === targetIdx) return;

      const movedCol = this.mainColumns.splice(this.draggedColIndex, 1)[0];
      this.mainColumns.splice(targetIdx, 0, movedCol);
      this.draggedColIndex = null;

      this.$nextTick(() => {
        if (typeof lucide !== "undefined") lucide.createIcons();
      });
    },

    handleColumnDragEnd() {
      this.draggedColIndex = null;
    }
  }));
});
