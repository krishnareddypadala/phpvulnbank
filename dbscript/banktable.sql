-- phpMyAdmin SQL Dump
-- version 4.6.6deb5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 09, 2020 at 11:14 AM
-- Server version: 5.7.32-0ubuntu0.18.04.1
-- PHP Version: 7.2.24-0ubuntu0.18.04.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bankdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `banktable`
--

CREATE TABLE `banktable` (
  `acno` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `balance` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(14) NOT NULL,
  `feedback` varchar(1000) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `banktable`
--

INSERT INTO `banktable` (`acno`, `username`, `password`, `balance`, `email`, `mobile`, `feedback`) VALUES
(1, 'krishna', 'happy123$', 2840, 'test@test.com', '9876543210', 'krishnarp'),
(2, 'admin', 'krishna1$', 4528, 'admin@test.com', '9876543201', 'hello'),
(3, 'murali', 'happy1$', 1030, 'tes@test.com', '8765432190', 'hello'),
(4, 'srikanth', 'test123$', 1020, 'test@test.com', '7654321098', 'hello');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `banktable`
--
ALTER TABLE `banktable`
  ADD PRIMARY KEY (`acno`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `banktable`
--
ALTER TABLE `banktable`
  MODIFY `acno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
