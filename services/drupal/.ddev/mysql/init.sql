-- Create D8 application database and user
CREATE DATABASE web;
CREATE USER 'web'@'%' IDENTIFIED BY 'web';
GRANT ALL ON web.* TO 'web'@'%';

-- Create D7 database and user
CREATE DATABASE web_d7;
CREATE USER 'web_d7'@'%' IDENTIFIED BY 'web_d7';
GRANT ALL ON web_d7.* TO 'web_d7'@'%';
