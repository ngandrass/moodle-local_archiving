# Troubleshooting

This section lists some common pitfalls that you might encounter when setting up or using this activity archiving
driver.


## A job timeouts prior to the configured timeout value

Check your timeout settings. Be aware that there is a configurable job timeout within the Moodle plugin settings
(`local_archiving | job_timeout_min`) as well as one within the archive worker service
(`MOODLE_ARCHIVER_REQUEST_TIMEOUT_SEC`).

!!! warning
    Since the shortest timeout always takes precedence, make sure to adjust **both** timeout settings as required.


## The archive worker service cannot access the Moodle instance

If the archive worker service can not access your Moodle instance, you should check the following:

1. Ensure that the archive worker service is able to resolve the hostname of your Moodle instance.
    - You can test this by running `nslookup yourmoodle.tld` on the machine that runs the archive worker service.
    - If the lookup fails, check the DNS settings on the machine that runs the archive worker service.
2. Ensure that the archive worker service is able to reach the machine that runs your Moodle instance.
    - You can test this by running `ping yourmoodle.tld` on the machine that runs the archive worker service.
    - If the ping fails, check the network and firewall settings on both machines.
3. Ensure that the archive worker service is able to retrieve a basic web page from your Moodle instance.
    - You can test this by running `curl -v https://yourmoodle.tld` on the machine that runs the archive worker service.
    - If the curl command fails, check the firewall settings on both machines.

!!! info "Proxy Servers"
    If your archive worker requires a proxy server to access your Moodle instance, you can find information on how to
    configure it here: [Worker Service > Proxy Servers](worker.md#proxy-servers)


## Access to Moodle webservice functions fails

If you get an error message that access to one or more webservice functions is denied, you should check the following:

1. Ensure that your archive worker service is able to [connect to your Moodle
   instance](#the-archive-worker-service-cannot-access-the-moodle-instance).
2. Ensure that webservices and the REST protocol are enabled globally.


## Upload of created archives fail

If the archive worker is able to create the archive but fails to upload it back to your Moodle instance, you should
check the following things:

1. Ensure that your Moodle is configured to file uploads. `$CFG->maxbytes` should be set to the same value as PHP 
   `upload_max_filesize` (see below).
2. Ensure you have configured PHP to accept file uploads. The `upload_max_filesize` and `post_max_size` settings
   in your `php.ini` should be set to a value that matches `$CFG->maxbytes` from your Moodle settings.
3. If you are using an ingress webserver and PHP-FPM via FastCGI, ensure that the `fastcgi_send_timeout` and
   `fastcgi_read_timeout` settings are long enough to allow the upload of the largest archive file that you expect.
   Nginx usually signals this problem by returning a '504 Gateway Time-out' after 60 seconds (default).
4. Ensure that your antivirus plugin is capable of handling large files. When using ClamAV you can control maximum file
   sizes by setting `MaxFileSize`, `MaxScanSize`, and `StreamMaxLength` (when using a TCP socket) inside `clamd.conf`.

!!! info "Chunked file uploads"
    The worker service automatically detects the maximum file upload size that is configured in your Moodle instance
    and uses chunked file uploads to transfer the archive back to Moodle whenever necessary. This allows you to keep the
    max file upload size to a reasonable value while still being able to create large archives.


## Text is not rendered correctly

If the text inside the generated PDF files is not rendered correctly, e.g., when only rectangles are displayed 
instead of characters, please make sure that an extended set of base fonts is available on your server.

If you are running Ubuntu or Debian, you can ensure this by installing the `fonts-noto` package via your package manager.

If you are using the official Docker image, please open a bug report instead.


## Readiness probe fails (MathJax, GeoGebra, ...)

Some question types contain content that is rendered asynchronously by JavaScript after the page has already been loaded
(e.g., MathJax, GeoGebra, ...). Therefore, the archive worker service contains a readiness probe that determines whether
all dynamic content has been rendered and delays PDF generation until everything is ready.

If your activity contains such dynamically rendered content and your archive jobs fail after a short time, you should
check the logs of your archive worker for messages like `Ready signal not received ` or similar.

At this point you can try increasing the number of seconds the archive worker waits before considering the check to have
failed via [`MOODLE_ARCHIVER_WAIT_FOR_READY_SIGNAL_TIMEOUT_SEC`](worker.md). If desired, you
can also make the archive worker simply continue after the timeout is reached and generating the PDF as is by setting
[`MOODLE_ARCHIVER_CONTINUE_AFTER_READY_SIGNAL_TIMEOUT=True`](worker.md).

If you believe that the readiness probe failure is caused by a bug, please do not hesitate to
[open a bug report](https://github.com/ngandrass/moodle-local_archiving/issues).
