# ETL with Web Server Assets
## ETL Pipeline for Cause & Risk Summaries

The Lancet Medical Journal publishes Cause and Risk Summaries which IHME supplies to them.  We also publish them to the web on our servers and The Lancet links to them in their periodicals.  The design is done and published on an internal site for review.   We use ETL to publish these to the web from our production servers. Changes can be made frequently near the time of publication, and the ETL automation is very helpful.  Also, the ETL pipeline automation reduces errors.
&nbsp;

**EXTRACT** - with JENKINS 100's of files (html, css, images, etc.) from another completely-different internal site and server was placed in a file system accessible to our production website code.
&nbsp;

**TRANSFORM** - data  had to be altered (eg image destinations specified in the HTML) for their proper destinations on our production server.  See below annotated screenshot of some of the code.
&nbsp;

**LOAD** - to create webpages on  the public website, data needed to be inserted in a special way into the Drupal Database (which is unlike any database one would architect) with the newly transformed HTML, CSS, image paths, etc.
&nbsp;

I coded all of this a while ago and this may not be my latest code. These 3 files represent only some of the code involved.
&nbsp;

![transform_annot](https://github.com/programweb/ETL-with-Web-Server-Assets/assets/12736699/a4e164b6-1686-4cba-94f4-aaf36a8b01e7)
&nbsp;

**EXTRACT**
&nbsp;

The Extraction is accomplished with a Jenkins pipeline.  This extracts files from a directory on one server and moves them to a directory on a publicly-available web server (or another server for testing and development).
&nbsp;

There is a pre-determined target path on the web server.  It is verified to exist.  A new directory is made on that path.  Then a rsync command is issued to bring the assets onto the web server.  Ownership and permissions are set for the added directories and files.
&nbsp;

&nbsp;

**TRANSFORM**
&nbsp;

The Transform process is a complicated step, but will be simplified here.
&nbsp;

Before transformation a number of validations are performed to assure the data is clean, consistent and will result in an expected webpage, title, URL, page metadata, images, etc.
&nbsp;

The transformation involves information supplied by one of the JSON metadata files and server-side processing.
&nbsp;

Using the metadata and server-side code, the titles and URLs of the pages are built by transformation; and, the original assets are located.
&nbsp;

File content is uploaded and parsed.  Body content from the files is transformed in a variety of ways (for example:  image paths must be on paths that the Linux Web Server can find—to do this, regular expressions and callback functions are employed).
&nbsp;

Even the metadata in the JSON can be transformed, for instance, to distinguish cause and impairment.
&nbsp;

Cascading StyleSheets (CSS) of the GBD 2-Pagerator are ignored for a number of reasons: (a) content width is more constrained on the public web server than the GBD 2-Pagerator which is very important in-terms of layout; (b) the public web server must handle mobile devices; (c) some of the web server styles must be overridden for the Cause and Risk Summaries to be displayed properly; (d) the public site must maintain navigation consistently with existing pages on the website; (e) even the navigation adjacent to and for the Cause and Risk Summaries on the public site differs from the GBD 2-Pagerator.  So, new stylesheets are built for the Cause and Risk Summaries on the public site; yet, there is an effort to obtain template information from the JSON metadata.
&nbsp;

&nbsp;

**LOAD**
&nbsp;

After the transform step, the transformed body content must be loaded in Drupal database tables.
&nbsp;

Computed titles, URLs, and other webpage information is saved in addition to the content.
&nbsp;

Dupal nodes are registered.
&nbsp;

The node already exists if there are subsequent ETLs involving the same page, in which case, updates are handled slightly differently.
&nbsp;

At this point, the GBD Cause and Risk Summary webpages are available on the  public website.  Hundreds of webpages are produced fairly rapidly.
&nbsp;
