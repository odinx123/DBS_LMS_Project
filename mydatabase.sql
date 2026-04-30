-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026 年 04 月 29 日 05:11
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

-- --------------------------------------------------------

--
-- 資料表結構 `enrollment`
--

CREATE TABLE `enrollment` (
  `Course_ID` varchar(50) NOT NULL,
  `Student_ID` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- 資料表結構 `student`
--

CREATE TABLE `student` (
  `Student_ID` varchar(50) NOT NULL COMMENT '學號',
  `Password_Hash` varchar(255) NOT NULL COMMENT '密碼雜湊',
  `Name` varchar(100) NOT NULL COMMENT '姓名',
  `Email` varchar(100) DEFAULT NULL COMMENT '電子郵件'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
