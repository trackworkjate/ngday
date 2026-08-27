document.addEventListener("alpine:init", () => {
  Alpine.data("excelImporter", () => ({
    isOpen: false,
    isDragging: false,
    selectedFile: null,
    boardNameInput: "",
    isUploading: false,
    uploadProgress: 0,
    importResult: null,
    errorMessage: "",

    openModal() {
      this.isOpen = true;
      this.selectedFile = null;
      this.importResult = null;
      this.errorMessage = "";
      this.uploadProgress = 0;
      this.boardNameInput = "";
    },

    closeModal() {
      this.isOpen = false;
      if (this.importResult && this.importResult.success) {
        // Trigger board reload if imported successfully
        window.location.reload();
      }
    },

    handleFileSelect(e) {
      const files = e.target.files || e.dataTransfer.files;
      if (files.length > 0) {
        this.selectedFile = files[0];
        if (!this.boardNameInput) {
          this.boardNameInput = this.selectedFile.name.replace(/\.[^/.]+$/, "");
        }
      }
    },

    async startImport() {
      if (!this.selectedFile) return;

      this.isUploading = true;
      this.uploadProgress = 20;
      this.errorMessage = "";
      this.importResult = null;

      const formData = new FormData();
      formData.append("file", this.selectedFile);
      if (this.boardNameInput) {
        formData.append("board_name", this.boardNameInput);
      }

      try {
        this.uploadProgress = 50;
        const res = await fetch("api/import/monday-excel", {
          method: "POST",
          body: formData
        });

        this.uploadProgress = 90;
        const data = await res.json();

        if (res.ok && data.success) {
          this.uploadProgress = 100;
          this.importResult = data;
        } else {
          this.errorMessage = data.error || "Failed to import Excel file.";
        }
      } catch (err) {
        this.errorMessage = "Network or server error during upload: " + err.message;
      } finally {
        this.isUploading = false;
      }
    }
  }));
});
