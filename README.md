# Nigiwai PM - Monday-Style Project Management Web Application

ระบบบริหารและติดตามความคืบหน้าโครงการก่อสร้างและขยายสาขา สไตล์ Monday.com พร้อมระบบ Dual Data Persistence (MySQL + JSON), Timeline Duration Engine, Custom Columns, และ Excel Importer

---

## 🌟 ฟังก์ชันเด่น (Key Features)

- **Interactive Monday-Style Board UI:** ออกแบบสไตล์ Monday.com แท้ๆ ด้วย Tailwind CSS, Alpine.js และ Lucide Icons
- **Timeline & Duration Counter:** เลือกวันที่เริ่มต้น-สิ้นสุดผ่าน Calendar Date Picker และคำนวณจำนวนวันรวมสด (Duration Days)
- **Branch Opening Tracker:** แสดงป้าย **Soft Opening** (🎀) และ **Grand Opening** (🎊) พร้อมแถบ **Duration Elapsed Progress Bar** ไล่สีเขียว-เหลือง-แดง เทียบกับวันที่ปัจจุบัน
- **Dynamic Progress Calculation:** คำนวณ Progress By Dept อัตโนมัติจากสัดส่วน Subtasks ที่ Done จริง
- **Overall Completed Fractional Badge:** แสดงความคืบหน้างานจริง เช่น 12/59 (20%), 29/59 (49%)
- **Drag & Drop & Resizable Columns:** สลับลำดับคอลัมน์ด้วยการลากวาง (Drag & Drop) และปรับความกว้างคอลัมน์ได้อิสระ
- **Column Management:** เพิ่มและลบคอลัมน์ได้ 7 ชนิด (Status, Date, Progress %, People, Number, Text, Long Text)
- **Save View (100% Guaranteed Persistence):** บันทึกการเปลี่ยนแปลงและมุมมองลง LocalStorage, Server JSON และ MySQL Database
- **Excel Importer Engine:** รองรับการนำเข้าไฟล์ Excel (.xlsx) เพื่อสร้างโครงสร้าง Group, Task, Subtask และ Updates อัตโนมัติ

---

## 🚀 สถาปัตยกรรมระบบ (Architecture)

- **Frontend:** HTML5, Alpine.js 3.x, Tailwind CSS, Lucide Icons
- **Backend:** PHP 8.x, Direct Action API Endpoint (pi/action.php)
- **Database:** MySQL / MariaDB (PDO Singleton) + Fail-Safe JSON Cache (data/board_data.json)
- **Web Server:** Apache / Nginx
