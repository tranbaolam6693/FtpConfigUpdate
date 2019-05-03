# FtpConfigUpdate

**Install**

Bỏ FtpConfig.php => /library/Custom/Controller/Batch/

Bỏ FtpConfigUpdate.php + FtpConfigRollback.php => /application/batch/controllers/

**Usage:**

_> php index.php development app FtpConfigUpdate {key} {old_value] {new_value} {date} {log_ftp_fail}_

_> php index.php development app FtpConfigRollback {version} {date} {log_ftp_fail}_



**Note::** 

{log_ftp_fail} có thể truyền hoặc không.

_Ví dụ file log là: /var/www/html/application/../log/Batch_FtpConfigUpdate_FtpLoginFail_1556855778.log_

_> Thì {log_ftp_fail} = Batch_FtpConfigUpdate_FtpLoginFail_1556855778_

Xem hình log khi chạy batch sẽ thấy log FTP Fail: http://prntscr.com/njud92

