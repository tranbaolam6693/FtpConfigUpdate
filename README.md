# FtpConfigUpdate

**Install**

Add `FtpConfig.php` => `/library/Custom/Controller/Batch/`

Add `FtpConfigUpdate.php` + `FtpConfigRollback.php` => `/application/batch/controllers/`

**Usage:**

_>  php index.php development app FtpConfigUpdate `type` `new_value` `param`_

_> php index.php development app FtpConfigRollback `version` `param`_

**Update Batch:**

_>  Example: php index.php development app FtpConfigUpdate `update` `http://google.com` `"2019-05-03 00:00:00"` _

Update: `param` bây giờ là optional (nếu không truyền thì lấy ngày giờ hiện tại). Company hiện tại là get all.

**Fix Update Batch (If connect FTP Fail):**

_>  Example: php index.php development app FtpConfigUpdate `fix` `http://google.com` `Batch_FtpConfigUpdate_FtpFail_1557110064_1543762800` _

**Rollback Batch:**

_> php index.php development app FtpConfigRollback `1.0` `Batch_FtpConfigUpdate_FtpSuccess_1557110064_1543762800`_

**Note::** 

_`version` được set trong file `FtpConfig.php`

_`type` chỉ được truyền `update` hoặc `fix`

_Ví dụ file log là: `/var/www/html/application/../log/Batch_FtpConfigUpdate_FtpLoginFail_1556855778.log`_

_> Thì `param` = `Batch_FtpConfigUpdate_FtpLoginFail_1556855778`_

