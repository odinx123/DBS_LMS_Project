-- 1. 插入額外測試學生 (Password_Hash 統一使用範例中的雜湊值)
INSERT INTO `student` (`Student_ID`, `Password_Hash`, `Name`, `Email`, `Class`) VALUES
('S002', '$2y$10$hKfiBgbmq1EzNnEAaHSi9OSrXJM3Qwz.1mEekvaJabjCMJXkOpP9m', '張小華', 's002@example.com', 'CS_01'),
('S003', '$2y$10$hKfiBgbmq1EzNnEAaHSi9OSrXJM3Qwz.1mEekvaJabjCMJXkOpP9m', '李大明', 's003@example.com', 'CS_01'),
('S004', '$2y$10$hKfiBgbmq1EzNnEAaHSi9OSrXJM3Qwz.1mEekvaJabjCMJXkOpP9m', '王小美', 's004@example.com', 'CS_01'),
('S005', '$2y$10$hKfiBgbmq1EzNnEAaHSi9OSrXJM3Qwz.1mEekvaJabjCMJXkOpP9m', '趙六', 's005@example.com', 'CS_01');

-- 2. 建立選課關係 (讓多位學生修習 C001 資料庫系統設計)
INSERT INTO `enrollment` (`Course_ID`, `Student_ID`) VALUES
('C001', 'S001'), -- 假設這是目前的帳號
('C001', 'S002'),
('C001', 'S003'),
('C001', 'S004'),
('C001', 'S005');

-- 3. 插入一個專門用來測試直方圖的作業
INSERT INTO `assignment` (`Assign_ID`, `Course_ID`, `Teacher_ID`, `Title`, `Description`, `Publish_Time`, `Due_Date`) VALUES
('asg_stat_test', 'C001', 'T001', '期末專題實作', '資料庫期末專題報告上傳', '2026-05-05 14:00:00', '2026-06-01 23:59:00');

-- 4. 插入多筆繳交紀錄與成績 (分佈於各分數段)
INSERT INTO `submission` (`Submit_ID`, `Assign_ID`, `Student_ID`, `File_Path`, `Submit_Time`, `Score`, `Comment`) VALUES
('sub_t1', 'asg_stat_test', 'S001', 'uploads/s01.pdf', NOW(), 95, '優異'),
('sub_t2', 'asg_stat_test', 'S002', 'uploads/s02.pdf', NOW(), 88, '良好'),
('sub_t3', 'asg_stat_test', 'S003', 'uploads/s03.pdf', NOW(), 75, '尚可'),
('sub_t4', 'asg_stat_test', 'S004', 'uploads/s04.pdf', NOW(), 62, '及格邊緣'),
('sub_t5', 'asg_stat_test', 'S005', 'uploads/s05.pdf', NOW(), 45, '內容不全，請重新準備'),
('sub_t6', 'asg_stat_test', 'S001', 'uploads/s01.pdf', NOW(), 92, '你的測試成績');

-- 5. 插入一筆助教帳號 (用於測試角色權限)
INSERT INTO `teacher` (`Teacher_ID`, `Password_Hash`, `Name`, `Email`, `Role`) VALUES
('TA001', '$2y$10$j0/vTFfsfC8A6RGlryzNn.CSGP8CcvKbxfbtqSkobwtxQSPS3Uv4i', '陳助教', 'ta@demo.com', 'ta');