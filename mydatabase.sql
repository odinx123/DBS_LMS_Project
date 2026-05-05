-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026 年 05 月 05 日 08:36
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `mydatabase`
--

-- --------------------------------------------------------

--
-- 資料表結構 `announcement`
--

CREATE TABLE `announcement` (
  `Announce_ID` varchar(50) NOT NULL COMMENT '公告編號',
  `Course_ID` varchar(50) NOT NULL,
  `Teacher_ID` varchar(50) DEFAULT NULL,
  `Title` varchar(255) NOT NULL COMMENT '標題',
  `Content` text DEFAULT NULL COMMENT '內容',
  `Publish_Time` datetime DEFAULT current_timestamp() COMMENT '發布時間',
  `Update_Time` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '修改時間'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `announcement`
--

INSERT INTO `announcement` (`Announce_ID`, `Course_ID`, `Teacher_ID`, `Title`, `Content`, `Publish_Time`, `Update_Time`) VALUES
('ann_0d839f839436ab3d', 'C001', 'T001', '【新作業】mail test 2', '課程已發布新作業：\n名稱：mail test 2\n說明：mail test 2 \r\nmail test 2 \r\nmail test 2 \r\n\r\nmail test 2\n截止日期：2026-05-28 14:15', '2026-05-05 14:15:10', '2026-05-05 14:15:10'),
('ann_33922fc371444516', 'C003', 'T001', 'test', '上傳程式作業', '2026-04-30 15:51:12', '2026-04-30 15:51:12'),
('ann_7b0c626f7c7040bf', 'C001', 'T001', '【新作業】hw2', '課程已發布新作業：\n名稱：hw2\n說明：練習sql語法\n截止日期：2026-05-10 15:19', '2026-05-02 15:20:31', '2026-05-02 15:20:31'),
('ann_8ec940a06e4cb768', 'C002', 'T001', '【新作業】mail test', '課程已發布新作業：\n名稱：mail test\n說明：mail test\n截止日期：2026-05-16 12:22', '2026-05-05 12:22:47', '2026-05-05 12:22:47'),
('ann_ce3d6663d0820f67', 'C001', 'T001', '報告', '上傳100頁pdf報告', '2026-04-30 15:52:09', '2026-04-30 15:52:09');

-- --------------------------------------------------------

--
-- 資料表結構 `assignment`
--

CREATE TABLE `assignment` (
  `Assign_ID` varchar(50) NOT NULL COMMENT '作業編號',
  `Course_ID` varchar(50) NOT NULL,
  `Teacher_ID` varchar(50) DEFAULT NULL,
  `Title` varchar(255) NOT NULL COMMENT '作業名稱',
  `Description` text DEFAULT NULL COMMENT '作業說明',
  `Publish_Time` datetime DEFAULT current_timestamp() COMMENT '發布時間',
  `Due_Date` datetime NOT NULL COMMENT '繳交期限'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `assignment`
--

INSERT INTO `assignment` (`Assign_ID`, `Course_ID`, `Teacher_ID`, `Title`, `Description`, `Publish_Time`, `Due_Date`) VALUES
('asg_1383901b880f3f1f', 'C001', 'T001', 'test', 'djfklsj', '2026-04-30 15:52:53', '2026-05-01 22:57:00'),
('asg_3c8b3d335bd75977', 'C002', 'T001', 'mail test', 'mail test', '2026-05-05 12:22:47', '2026-05-16 12:22:00'),
('asg_9d9cdca8b8c1b0dc', 'C002', 'T001', 'sdfj', '開發個網頁讓100萬人使用並上傳證明和網頁', '2026-04-30 15:54:03', '2026-05-02 15:53:00'),
('asg_def99a542747ee0c', 'C001', 'T001', 'hw2', '練習sql語法', '2026-05-02 15:20:31', '2026-05-10 15:19:00');

-- --------------------------------------------------------

--
-- 資料表結構 `course`
--

CREATE TABLE `course` (
  `Course_ID` varchar(50) NOT NULL COMMENT '課程編號',
  `Course_Name` varchar(100) NOT NULL COMMENT '課程名稱',
  `Semester` varchar(20) NOT NULL COMMENT '學期',
  `Teacher_ID` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `course`
--

INSERT INTO `course` (`Course_ID`, `Course_Name`, `Semester`, `Teacher_ID`) VALUES
('C001', '資料庫系統設計', '114-1', 'T001'),
('C002', '進階網頁開發', '114-1', 'T001'),
('C003', '演算法與資料結構', '114-1', 'T001');

-- --------------------------------------------------------

--
-- 資料表結構 `enrollment`
--

CREATE TABLE `enrollment` (
  `Course_ID` varchar(50) NOT NULL,
  `Student_ID` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `enrollment`
--

INSERT INTO `enrollment` (`Course_ID`, `Student_ID`) VALUES
('C001', 'S001'),
('C002', 'S001');

-- --------------------------------------------------------

--
-- 資料表結構 `student`
--

CREATE TABLE `student` (
  `Student_ID` varchar(50) NOT NULL COMMENT '學號',
  `Password_Hash` varchar(255) NOT NULL COMMENT '密碼雜湊',
  `Name` varchar(100) NOT NULL COMMENT '姓名',
  `Email` varchar(100) DEFAULT NULL COMMENT '電子郵件',
  `Class` varchar(20) DEFAULT 'Default'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `student`
--

INSERT INTO `student` (`Student_ID`, `Password_Hash`, `Name`, `Email`, `Class`) VALUES
('S001', '$2y$10$hKfiBgbmq1EzNnEAaHSi9OSrXJM3Qwz.1mEekvaJabjCMJXkOpP9m', '陳小明', 'm143040012@student.nsysu.edu.tw', 'Default');

-- --------------------------------------------------------

--
-- 資料表結構 `submission`
--

CREATE TABLE `submission` (
  `Submit_ID` varchar(50) NOT NULL COMMENT '繳交紀錄編號',
  `Assign_ID` varchar(50) NOT NULL,
  `Student_ID` varchar(50) NOT NULL,
  `File_Path` varchar(512) DEFAULT NULL COMMENT '伺服器檔案路徑',
  `Submit_Time` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '最後上傳時間',
  `Score` float DEFAULT NULL COMMENT '作業成績',
  `Comment` text DEFAULT NULL COMMENT '教師評語'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `submission`
--

INSERT INTO `submission` (`Submit_ID`, `Assign_ID`, `Student_ID`, `File_Path`, `Submit_Time`, `Score`, `Comment`) VALUES
('sub_b8c2da0a3b367387', 'asg_1383901b880f3f1f', 'S001', 'uploads/submissions/b907c6843d0e78f1afb9511ff2bce974.pdf', '2026-05-02 15:12:36', 50, '糟透了\r\n糟透了\r\n糟透了\r\n糟透了\r\n糟透了\r\n糟透了\r\n糟透了糟透了糟透了糟透了糟透了糟透了');

-- --------------------------------------------------------

--
-- 資料表結構 `teacher`
--

CREATE TABLE `teacher` (
  `Teacher_ID` varchar(50) NOT NULL COMMENT '教師/助教編號',
  `Password_Hash` varchar(255) NOT NULL COMMENT '密碼雜湊',
  `Name` varchar(100) NOT NULL COMMENT '姓名',
  `Email` varchar(100) DEFAULT NULL COMMENT '電子郵件',
  `Role` enum('teacher','ta') NOT NULL DEFAULT 'teacher' COMMENT '角色'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `teacher`
--

INSERT INTO `teacher` (`Teacher_ID`, `Password_Hash`, `Name`, `Email`, `Role`) VALUES
('T001', '$2y$10$j0/vTFfsfC8A6RGlryzNn.CSGP8CcvKbxfbtqSkobwtxQSPS3Uv4i', '王老師', 'teacher@demo.com', 'teacher');

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `announcement`
--
ALTER TABLE `announcement`
  ADD PRIMARY KEY (`Announce_ID`),
  ADD KEY `Course_ID` (`Course_ID`),
  ADD KEY `Teacher_ID` (`Teacher_ID`);

--
-- 資料表索引 `assignment`
--
ALTER TABLE `assignment`
  ADD PRIMARY KEY (`Assign_ID`),
  ADD KEY `Course_ID` (`Course_ID`),
  ADD KEY `Teacher_ID` (`Teacher_ID`);

--
-- 資料表索引 `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`Course_ID`),
  ADD KEY `Teacher_ID` (`Teacher_ID`);

--
-- 資料表索引 `enrollment`
--
ALTER TABLE `enrollment`
  ADD PRIMARY KEY (`Course_ID`,`Student_ID`),
  ADD KEY `Student_ID` (`Student_ID`);

--
-- 資料表索引 `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`Student_ID`);

--
-- 資料表索引 `submission`
--
ALTER TABLE `submission`
  ADD PRIMARY KEY (`Submit_ID`),
  ADD KEY `Assign_ID` (`Assign_ID`),
  ADD KEY `Student_ID` (`Student_ID`);

--
-- 資料表索引 `teacher`
--
ALTER TABLE `teacher`
  ADD PRIMARY KEY (`Teacher_ID`);

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `announcement`
--
ALTER TABLE `announcement`
  ADD CONSTRAINT `announcement_ibfk_1` FOREIGN KEY (`Course_ID`) REFERENCES `course` (`Course_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_ibfk_2` FOREIGN KEY (`Teacher_ID`) REFERENCES `teacher` (`Teacher_ID`) ON DELETE SET NULL;

--
-- 資料表的限制式 `assignment`
--
ALTER TABLE `assignment`
  ADD CONSTRAINT `assignment_ibfk_1` FOREIGN KEY (`Course_ID`) REFERENCES `course` (`Course_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignment_ibfk_2` FOREIGN KEY (`Teacher_ID`) REFERENCES `teacher` (`Teacher_ID`) ON DELETE SET NULL;

--
-- 資料表的限制式 `course`
--
ALTER TABLE `course`
  ADD CONSTRAINT `course_ibfk_1` FOREIGN KEY (`Teacher_ID`) REFERENCES `teacher` (`Teacher_ID`) ON DELETE SET NULL;

--
-- 資料表的限制式 `enrollment`
--
ALTER TABLE `enrollment`
  ADD CONSTRAINT `enrollment_ibfk_1` FOREIGN KEY (`Course_ID`) REFERENCES `course` (`Course_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollment_ibfk_2` FOREIGN KEY (`Student_ID`) REFERENCES `student` (`Student_ID`) ON DELETE CASCADE;

--
-- 資料表的限制式 `submission`
--
ALTER TABLE `submission`
  ADD CONSTRAINT `submission_ibfk_1` FOREIGN KEY (`Assign_ID`) REFERENCES `assignment` (`Assign_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_ibfk_2` FOREIGN KEY (`Student_ID`) REFERENCES `student` (`Student_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
