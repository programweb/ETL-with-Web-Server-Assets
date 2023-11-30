# ETL with Web Server Assets

**EXTRACT** - with JENKINS 100's of files (html, css, images, etc.) from another completely-different internal site and server was placed in a file system accessible to our production website code.
&nbsp;

**TRANSFORM** - data  had to be altered (eg image destinations specified in the HTML) for their proper destinations on our production server.  See below annotated screenshot of some of the code.
&nbsp;

**LOAD** - to create webpages on  the public website, data needed to be inserted in a special way into the Drupal Database (which is unlike any database one would architect) with the newly transformed HTML, CSS, image paths, etc.
&nbsp;

I coded all of this a while ago and this may not be my latest code. These 3 files represent only some of the code involved.
