SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

\! echo " * Initializing..... Database..!!";
CREATE USER 'groot'@'%' IDENTIFIED BY 'bose123$';
GRANT ALL PRIVILEGES ON *.* TO 'groot'@'%' WITH GRANT OPTION;

CREATE DATABASE bankdb;
USE bankdb;
CREATE TABLE `banktable` (
  `acno` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `balance` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mobile` varchar(14) NOT NULL,
  `feedback` varchar(1000) NOT NULL,
  `active` TINYINT NULL DEFAULT 0,
  `admin` TINYINT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `banktable` (`acno`, `username`, `password`, `balance`, `email`, `mobile`, `feedback`,`active`,`admin`) VALUES
(1, 'krishna', md5('happy123$'), 2840, 'test@test.com', '9876543210', 'krishnarp','1','0'),
(2, 'admin', md5('krishna1$'), 4528, 'admin@test.com', '9876543201', 'hello','1','1'),
(3, 'murali', md5('happy1$'), 1030, 'tes@test.com', '8765432190', 'hello','1','0'),
(4, 'srikanth', md5('test123$'), 1020, 'test@test.com', '7654321098', 'hello','0','0');

ALTER TABLE `banktable`
  ADD PRIMARY KEY (`acno`);
ALTER TABLE `banktable`
  MODIFY `acno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
