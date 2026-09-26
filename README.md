# PPK Asset & Repair Management System

ระบบบริหารจัดการงานซ่อมบำรุงและทะเบียนทรัพย์สิน โรงพยาบาลพระปกเกล้า

## ภาพรวมโครงการ (Project Overview)

ระบบนี้ถูกพัฒนาขึ้นเพื่อยกระดับการจัดการงานซ่อมบำรุงทรัพย์สินและครุภัณฑ์ภายในองค์กร โดยเน้นความโปร่งใสของข้อมูล ความรวดเร็วในการให้บริการ (SLA) และการสรุปผลเชิงสถิติที่แม่นยำ เพื่อนำไปสู่การวางแผนซ่อมบำรุงเชิงป้องกัน (Preventive Maintenance) ในอนาคต

## คุณสมบัติหลัก (Key Features)

- **ระบบแจ้งซ่อมและติดตามสถานะ**: แจ้งซ่อมผ่านระบบพร้อมแนบรูปถ่าย และติดตามสถานะแบบ Real-time
- **ระบบทะเบียนทรัพย์สิน (Asset Registry)**: เชื่อมต่อข้อมูลครุภัณฑ์ ประวัติการซ่อม และสถานะการใช้งานอัตโนมัติ
- **SLA Dashboard**: ติดตามความเร็วในการตอบสนอง (Response) และการแก้ไขปัญหา (Resolution) เทียบกับเป้าหมาย
- **Technician Leaderboard**: ระบบประเมินประสิทธิภาพและจัดอันดับช่างตามผลงานและความพึงพอใจ
- **Live Chat Communication**: ช่องทางสื่อสารระหว่างผู้แจ้งและช่างซ่อมภายในใบงาน

## มาตรฐานการออกแบบ (Design & UI Standards)

เพื่อให้ระบบมีความเป็นมืออาชีพและใช้งานง่าย (User-Centric Design) จึงมีการกำหนดมาตรฐานดังนี้:

- **Typography**: ใช้ฟอนต์ Inter เป็นมาตรฐานหลัก
    - หัวข้อ (Titles): font-semibold (600)
    - เนื้อหา (Body): font-medium (500) หรือ font-normal (400)
    - งดใช้ความหนาระดับ Black (900) เพื่อความสะอาดตา
- **Flat UI Initiative**: เน้นการออกแบบสไตล์ Minimal Flat
    - ลดการใช้เงา (Shadows) ในระดับ Card และ Button
    - เน้นการใช้สีพื้นหลังและเส้นขอบ (Borders) ที่บางเบาเพื่อแยกส่วนการใช้งาน
- **Data Integrity**: ข้อมูลสถานะครุภัณฑ์และใบแจ้งซ่อมจะเชื่อมโยงกัน (Sync) ตลอดเวลา

## การเข้าใช้งานระบบ (Access)

- **Application URL**: [http://localhost:8000](http://localhost:8000)

### Authentication (trial system)

Sign-in here is a **trial setup for local / internal testing**, not the final login. People register themselves on `/register` (13-digit
citizen ID, name, password) and get a plain member account; an admin then sets their role and department. Nothing checks that the
citizen ID belongs to the person who typed it, and that is deliberate for now.

The real login will be built on the hospital's own personnel database, once this system is connected to it and the staff records can be
pulled from there. Until then, treat every account as unverified, and do not put this instance where the public can reach it. The HIS
asset lookup is in the same state: `HisAssetSyncService::getMockHisData()` is a mock that answers for any number, to be replaced by the
hospital's API (the field mapping is in `mapHisPayload()`, in one place).

### หมายเหตุการพัฒนา (Development Notes)

- **Vite Dev Server**: ระบบใช้ Vite สำหรับการโหลด CSS และ Assets ผ่านพอร์ต **4000**
- หากมีการเปลี่ยนแปลงการตั้งค่าพอร์ต โปรดตรวจสอบที่ไฟล์ `vite.config.js` และไฟล์การตั้งค่าที่เกี่ยวข้อง

## โครงสร้างทางเทคนิค (Technical Stack)

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Tailwind CSS 4.0, Alpine.js, Blade Templates
- **Database**: MySQL
- **Real-time**: Vite HMR, Redis (Optional)

---

Developed for PPK Hospital. All rights reserved.
