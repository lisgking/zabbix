       Readme file for the IBM OpenAdmin Tool (OAT) for Informix

3.11, March 2013

Contents

1.0 IBM OpenAdmin Tool (OAT) for Informix
2.0 Installing OAT
3.0 Installing the OpenAdmin Tool Community Edition
  3.1 Download packages
  3.2 Prerequisites
  3.3 Minimum version requirements
  3.4 Installation instructions
  3.5 Uninstalling OAT after manually installing
  3.6 Upgrading from a previous version of OAT
4.0 Installation notes
  4.1 Avoiding page timeout errors
  4.2 XAMPP
  4.3 Informix PDO driver
5.0 Configuring connectivity for high availability clusters
6.0 Adding a plug-in to OAT
7.0 OpenAdmin Tool support forums
8.0 Notices
  8.1 Trademarks

1.0 IBM OpenAdmin Tool (OAT) for Informix

The IBM® OpenAdmin Tool (OAT) for Informix® is a web application for
administering and analyzing the performance of IBM Informix database
servers. You can administer multiple database server instances from a
single OAT installation on a web server. You can access the web server
through any browser to administer all your database servers.

OAT includes these optional plug-ins: the IBM Informix Health Advisor
Plug-in for OpenAdmin Tool (OAT), the IBM Informix Replication Plug-in
for OpenAdmin Tool (OAT), the IBM Informix Schema Manager Plug-in for
OpenAdmin Tool (OAT), and the IBM InformixTimeSeries Plug-in for
OpenAdmin Tool (OAT). You can create additional plug-ins for OAT to add
the functions that you need.

Prerequisites: OAT, the Schema Manager plug-in, and the TimeSeries
plug-in require Informix 11.10 or later. The Health Advisor plug-in
requires Informix 11.50.xC7 or later. The Replication plug-in requires
Informix 11.50.xC4 or later.

2.0 Installing OAT

The IBM OpenAdmin Tool (OAT) for Informix is installed by default on the
supported platforms during a typical installation of the IBM Informix
Client Software Development Kit (Client SDK) or when you install the IBM
Informix software bundle and select Client SDK or Informix Connect. This
installation program includes OAT, the Health Advisor plug-in, the
Replication plug-in, the Schema Manager plug-in, the TimeSeries plug-in,
and all necessary software to run OAT, including pre-configured Apache,
PHP, and PDO_informix. For more information, see the topics in the
Informix information center on installing IBM OpenAdmin Tool (OAT) for
Informix with Client SDK:
http://pic.dhe.ibm.com/infocenter/informix/v121/topic/com.ibm.cpi.doc/ids_cpi_027.htm.

Alternatively, you can download the OpenAdmin Tool Community Edition,
which requires manual installation and does not include Apache, PHP, or
PDO_informix. This readme file provides information for downloading and
installing the OpenAdmin Tool Community Edition.

3.0 Installing the OpenAdmin Tool Community Edition

The OpenAdmin Tool Community Edition is installed manually. The Health
Advisor plug-in, the Replication plug-in, the Schema Manager plug-in,
and the TimeSeries plug-in can be installed with the OAT Community
Edition.

Prerequisite: To manually install OAT, you must have working knowledge
of web servers and PHP. If you do not have this knowledge, you might
need assistance from a web server administrator in your organization.

3.1 Download packages

You can download the OpenAdmin Tool Community Edition from the IBM
Informix Free Product Download page:
https://www14.software.ibm.com/webapp/iwm/web/preLogin.do?lang=en_US&source=swg-informixfpd.
These files are available to download:

Table 1. OAT Download Packages
+----------------------------------+----------------------------------+
| Download                         | Description                      |
+----------------------------------+----------------------------------+
| oatidsV3.11.tar                  | OpenAdmin Tool Community Edition |
|                                  | packaged as a tar file. The      |
|                                  | package includes OAT, the Health |
|                                  | Advisor plug-in, the Replication |
|                                  | plug-in, the Schema Manager      |
|                                  | plug-in, and the TimeSeries      |
|                                  | plug-in. It does not include     |
|                                  | Apache, PHP, PDO_informix, or    |
|                                  | IBM Informix Connect.            |
+----------------------------------+----------------------------------+
| oatidsV3.11.zip                  | OpenAdmin Tool Community Edition |
|                                  | packaged as a compressed file.   |
|                                  | The package includes OAT, the    |
|                                  | Health Advisor plug-in, the      |
|                                  | Replication plug-in, the Schema  |
|                                  | Manager plug-in, and the         |
|                                  | TimeSeries plug-in. It does not  |
|                                  | include Apache, PHP,             |
|                                  | PDO_informix, or IBM Informix    |
|                                  | Connect.                         |
+----------------------------------+----------------------------------+
| README.html                      | Instructions on how to install   |
|                                  | the OpenAdmin Tool Community     |
|                                  | Edition. (This document.)        |
+----------------------------------+----------------------------------+
| RELEASENOTES.html                | Fixed defects for the release.   |
|                                  | Link to new features.            |
+----------------------------------+----------------------------------+

3.2 Prerequisites

OAT is platform independent. You can manually install and run OAT on any
operating system for which you can set up and run the prerequisites
listed in this section.

OAT requires that the following products are installed. The versions in
parentheses indicate the versions with which OAT has been tested.

*  A web server (Apache 2.2.22, 2.4.2)
*  IBM Informix Connect or IBM Informix Client Software Development Kit
   (Client SDK) (4.10.xC1)
*  PHP 5.2.4 (minimum) compiled with PDO, PDO_SQLITE, GD, and
   SOAP-enabled (5.2.4, 5.4.4)
*  Informix PDO Module
*  Adobe Flash Player (11)

Important: The PHP and the PDO must come from the same PHP version.

3.3 Minimum version requirements

Table 2 describes the minimum version requirements for products that OAT
uses:

Table 2. Minimum Version Requirements
+----------------------------------+----------------------------------+
|             Product              |             Version              |
+----------------------------------+----------------------------------+
| Mozilla Firefox web browser      |                10                |
| (Recommended)                    |                                  |
+----------------------------------+----------------------------------+
| Microsoft Internet Explorer web  |               8.0                |
| browser                          |                                  |
+----------------------------------+----------------------------------+
| Apple Safari web browser         |                5                 |
+----------------------------------+----------------------------------+
| IBM Informix database server     |              11.10               |
+----------------------------------+----------------------------------+
| PHP                              |              5.2.4               |
+----------------------------------+----------------------------------+
| Adobe Flash Player               |                11                |
+----------------------------------+----------------------------------+

3.4 Installation instructions

To install the OpenAdmin Tool Community Edition:

1. Install and set up a working web server that is configured to serve
   PHP pages. For more information, see the installation notes section
   following the installation instructions.

   For further help, consult the web server product documentation for
   installation instructions, or contact the product customer service
   representatives for assistance.

2. Update the PHP configuration file (php.ini).
   a. Open the php.ini file for editing.

      If you are using XAMPP:

      *  Windows: Edit the php.ini file in the apache/bin folder, not in
         the php folder.
      *  Linux: Edit the php.ini file in the /etc folder.
      *  Mac OS X: Edit the php.ini file in the /Applications/xampp/etc
         folder.

      If you are not sure about the location of the correct php.ini
      configuration file, run the phpinfo() command within a PHP script
      on your web server to determine the location of the correct
      php.ini file.

   b. Add the following two lines after the extension=php_pdo.dll line
      to the "extension" section of the configuration file:

      

      extension=php_pdo_informix.dll
      extension=php_pdo_sqlite.dll 

      If these lines are already present, remove any comment indicators
      from in front of them. 

      Important: These lines must follow the extension=php_pdo.dll.

   c. Modify the memory_limit parameter to be at least 256 MB.

3. Install Informix Connect or Client SDK.
4. Extract the OAT package into your web server document root directory
   rootdir/OATINSTALL. For example: 
   *  Windows: C:\xampp\htdocs\oat
   *  UNIX or Linux: /usr/local/apache2/htdocs/oat
   *  Mac OS X: /Applications/xampp/htdocs/oat

   Important: For security reasons, set password protection for the OAT
   administration web pages. For more information and an example, see
   "Providing password protection for the Admin configuration pages:
   Apache example" in the OAT Help or in the Informix information center
   at
   http://pic.dhe.ibm.com/infocenter/informix/v121/topic/com.ibm.oat.doc/ids_oat_071.htm.

5. UNIX, Linux, or Mac OS X users: Change user or group ownership and
   permissions on the OAT directories. 
   a. Determine which user and group run the Apache (httpd) server from
      the httpd.conf file.
   b. Change ownership for each OAT subdirectory to the user and group
      that run the Apache (httpd) server.
   c. Grant write permissions to all OAT subdirectories and files. 

      The syntax for changing the ownership and permissions is:

      chown <user>:<group> <OATINSTALL>; chmod +w <OATINSTALL>;

      Where <OATINSTALL> is the root directory for OAT, the same
      directory that you used in "step 4".

6. In the web server environment, set the INFORMIXDIR environment
   variable to the Informix Connect or Client SDK installation location.
   
   *  Windows: Click Start > Control Panel, and then click System. In
      the System Properties dialog box, click the Advanced tab, and then
      click Environment Variables. Add or edit the system variable
      INFORMIXDIR.
   *  UNIX, Linux, and Mac OS X: Set the environment variable in the
      shell.

7. Start the web server.

   If you started the web server before modifying the php.ini file or
   setting INFORMIXDIR in the environment, restart the web server for
   the changes to take effect.

8. To start the OAT installer, go to
   http://SERVERNAME/OATINSTALL/install 

   Where:

    
   *  SERVERNAME is the name of the server where the web server is
      running
   *  OATINSTALL is the location of OAT within your web server document
      root directory, where you extracted the compressed file in "step
      4".

   For example: http://localhost/oat/install

9. Follow the instructions in the OAT installation screens: 
   a. Read and accept the license agreement.
   b. Verify that all the required PHP modules are installed. If so,
      click Next; if not, check your PHP installation.
   c. Modify and verify the following configuration parameters: 
      *  LANG: Specify the default language for the application screens.
      *  CONNDBDIR: Set the directory for the connections database. 

         Important: For security purposes, specify a secure directory
         for the connections.db file. Do not use the web server document
         root directory. Additionally, ensure that the directory in
         which the connections.db file is located is accessible by the
         user who runs Apache.

      *  BASEURL: Specify the root URL used to start OAT:
         http://SERVERNAME/OATINSTALL

         For example: http://localhost:8080/OAT

      *  HOMEDIR: Verify the directory into which you extracted the
         installation package.
      *  SECURESQL: Specify whether to prompt for login credentials when
         using SQLToolbox.
      *  INFORMIXCONTIME: Specify the number of seconds that OAT waits
         to connect to the database server before returning an error.
      *  INFORMIXCONRETRY: Specify the number of times that OAT attempts
         to connect to the database server during the connect time
         specified by INFORMIXCONTIME.

   d. To create the connections database, click Next. The connections
      database is created. Click Next to continue.
   e. Select the plug-ins to install and accept the plug-in license
      agreements.

10. Optional: To access a database with a locale that is not included in
   Client SDK, install the locale by using the IBM Informix
   International Language Supplement (ILS). OAT requires the UTF-8
   locales for all the databases that you access through OAT. After you
   install OAT, install ILS on the machine where OAT is installed, in
   the Informix Connect or Client SDK directory. Use ILS to install the
   additional locale, including the UTF-8 version, in the Informix
   Connect or Client SDK directory that OAT is using. The database name
   must be in English characters.
11. When the installation is completed, go to the OAT root URL:
   http://SERVERNAME/OATINSTALL.
12. On the login page, select Admin and then expand Manage Connections
   to add an Informix 11.10 (or later) connection to the default group,
   or to create a group.

   If you are using an Informix Connect or Client SDK version before
   V3.0, for each new connection that you add, ensure that there is a
   corresponding SQLHOSTS entry for that connection on the web server
   machine.

   To connect to a server, after a connection is created, click Login.
   Click Get Servers to retrieve the list of connections that you have
   created, and then select the server.

3.5 Uninstalling OAT after manually installing

Important: If you installed OAT with Client SDK, see the topic in the
Informix Information Center on uninstalling OAT after installing with
Client SDK. Do not remove OAT by manually deleting the files.

To manually uninstall OpenAdmin Tool Community Edition, delete the
OATINSTALL directory.

3.6 Upgrading from a previous version of OAT

To upgrade to OpenAdmin Tool Community Edition 3.11 from a previous
version, uninstall the old version and install the new version in the
same directory. OAT 2.20 and later preserves the connection database
information.

4.0 Installation notes

The following section provides additional information about configuring
OAT and other support resources.

4.1 Avoiding page timeout errors

Set the Informix Connect or Client SDK variables INFORMIXCONRETRY and
INFORMIXCONTIME to avoid page timeout errors for invalid connection
information. The recommended settings are:

*  INFORMIXCONRETRY=1
*  INFORMIXCONTIME=3

4.2 XAMPP

For manual installation, XAMPP is an easy-to-install Apache distribution
containing MySQL, PHP, and Perl. You can download XAMPP from the
following website: http://www.apachefriends.org/en/xampp.html.

For more detailed instructions on how to install OAT by using XAMPP,
refer to the following articles:

*  Installing IDSAdmin in a Windows XAMPP environment
*  OpenAdmin Tool XAMPP Installation on Linux

4.3 Informix PDO driver

The following developerWorks® article contains useful information to
assist with building an Informix PDO driver: A step-by-step how-to guide
to install, configure, and test a Linux, Apache, Informix, and PHP
server.

5.0 Configuring connectivity for high availability clusters

If you are using the high availability clusters features from OAT to
start and stop secondary servers or to add SD secondary servers, you
must configure the following daemons on the Informix server:

*  xinetd or inetd: 
   *  Linux - xinetd.
   *  UNIX - inetd: See your operating system information.

*  idsd: Installed with IBM Informix 11.50 and later. It is located in
   the $INFORMIXDIR/bin directory. For information and examples for
   configuring idsd, see "Configuring connectivity for high availability
   clusters" in the OAT Help or in the Informix information center at
   http://pic.dhe.ibm.com/infocenter/informix/v121/topic/com.ibm.oat.doc/ids_oat_059.htm.

   Prerequisite: Informix 11.50 or later is required to use idsd. Also,
   idsd is not currently available on Mac OS X or Windows. Without idsd,
   the high availability cluster management interface is available in
   OAT, but the feature set is limited.

6.0 Adding a plug-in to OAT

You can add plug-ins created by IBM or by other organizations to OAT.
Plug-ins created by IBM are included in the automated and manual
installations for OAT. For instructions on installing a plug-in from
another organization, see the OAT Help or in the Informix information
center at
http://pic.dhe.ibm.com/infocenter/informix/v121/topic/com.ibm.oat.doc/ids_oat_000.htm.

7.0 OpenAdmin Tool support forums

To ask questions, exchange ideas, and share solutions with your peers in
the Informix community, visit the following forums:

*  International Informix Users Group (IIUG) OpenAdmin Tool forum:
   http://www.iiug.org/forums/oat 
*  IBM Informix Developer and User Forum:
   http://www.ibm.com/developerworks/forums/forum.jspa?forumID=548

8.0 Notices

This information was developed for products and services offered in the
U.S.A.

IBM may not offer the products, services, or features discussed in this
document in other countries. Consult your local IBM representative for
information on the products and services currently available in your
area. Any reference to an IBM product, program, or service is not
intended to state or imply that only that IBM product, program, or
service may be used. Any functionally equivalent product, program, or
service that does not infringe any IBM intellectual property right may
be used instead. However, it is the user's responsibility to evaluate
and verify the operation of any non-IBM product, program, or service.

IBM may have patents or pending patent applications covering subject
matter described in this document. The furnishing of this document does
not grant you any license to these patents. You can send license
inquiries, in writing, to: 

IBM Director of Licensing
IBM Corporation
North Castle Drive
Armonk, NY 10504-1785
U.S.A. 

For license inquiries regarding double-byte (DBCS) information, contact
the IBM Intellectual Property Department in your country or send
inquiries, in writing, to: 

Intellectual Property Licensing
Legal and Intellectual Property Law
IBM Japan, Ltd.
19-21, Nihonbashi-Hakozakicho, Chuo-ku
Tokyo 103-8510, Japan

The following paragraph does not apply to the United Kingdom or any
other country where such provisions are inconsistent with local law:
INTERNATIONAL BUSINESS MACHINES CORPORATION PROVIDES THIS PUBLICATION
"AS IS" WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESS OR IMPLIED,
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
NON-INFRINGEMENT, MERCHANTABILITY OR FITNESS FOR A PARTICULAR PURPOSE.
Some states do not allow disclaimer of express or implied warranties in
certain transactions, therefore, this statement may not apply to you.

This information could include technical inaccuracies or typographical
errors. Changes are periodically made to the information herein; these
changes will be incorporated in new editions of the publication. IBM may
make improvements and/or changes in the product(s) and/or the program(s)
described in this publication at any time without notice.

Any references in this information to non-IBM websites are provided for
convenience only and do not in any manner serve as an endorsement of
those websites. The materials at those websites are not part of the
materials for this IBM product and use of those websites is at your own
risk.

IBM may use or distribute any of the information you supply in any way
it believes appropriate without incurring any obligation to you.

Licensees of this program who wish to have information about it for the
purpose of enabling: (i) the exchange of information between
independently created programs and other programs (including this one)
and (ii) the mutual use of the information which has been exchanged,
should contact: 

IBM Corporation
J46A/G4
555 Bailey Avenue
San Jose, CA 95141-1003
U.S.A.

Such information may be available, subject to appropriate terms and
conditions, including in some cases, payment of a fee.

The licensed program described in this document and all licensed
material available for it are provided by IBM under terms of the IBM
Customer Agreement, IBM International Program License Agreement or any
equivalent agreement between us.

Any performance data contained herein was determined in a controlled
environment. Therefore, the results obtained in other operating
environments may vary significantly. Some measurements may have been
made on development-level systems and there is no guarantee that these
measurements will be the same on generally available systems.
Furthermore, some measurements may have been estimated through
extrapolation. Actual results may vary. Users of this document should
verify the applicable data for their specific environment.

Information concerning non-IBM products was obtained from the suppliers
of those products, their published announcements or other publicly
available sources. IBM has not tested those products and cannot confirm
the accuracy of performance, compatibility or any other claims related
to non-IBM products. Questions on the capabilities of non-IBM products
should be addressed to the suppliers of those products.

All statements regarding IBM's future direction or intent are subject to
change or withdrawal without notice, and represent goals and objectives
only.

All IBM prices shown are IBM's suggested retail prices, are current and
are subject to change without notice. Dealer prices may vary.

This information is for planning purposes only. The information herein
is subject to change before the products described become available.

This information contains examples of data and reports used in daily
business operations. To illustrate them as completely as possible, the
examples include the names of individuals, companies, brands, and
products. All of these names are fictitious and any similarity to the
names and addresses used by an actual business enterprise is entirely
coincidental.

COPYRIGHT LICENSE:

This information contains sample application programs in source
language, which illustrate programming techniques on various operating
platforms. You may copy, modify, and distribute these sample programs in
any form without payment to IBM, for the purposes of developing, using,
marketing or distributing application programs conforming to the
application programming interface for the operating platform for which
the sample programs are written. These examples have not been thoroughly
tested under all conditions. IBM, therefore, cannot guarantee or imply
reliability, serviceability, or function of these programs. The sample
programs are provided "AS IS", without warranty of any kind. IBM shall
not be liable for any damages arising out of your use of the sample
programs.

Each copy or any portion of these sample programs or any derivative
work, must include a copyright notice as follows:

© (your company name) (year). Portions of this code are derived from IBM
Corp. Sample Programs.

© Copyright IBM Corp. _enter the year or years_. All rights reserved.

If you are viewing this information softcopy, the photographs and color
illustrations may not appear.

8.1 Trademarks

IBM, the IBM logo, and ibm.com are trademarks or registered trademarks
of International Business Machines Corp., registered in many
jurisdictions worldwide. Other product and service names might be
trademarks of IBM or other companies. A current list of IBM trademarks
is available on the web at "Copyright and trademark information" at
http://www.ibm.com/legal/copytrade.shtml.

Adobe, the Adobe logo, and PostScript are either registered trademarks
or trademarks of Adobe Systems Incorporated in the United States, and/or
other countries.

Genero and its logo are registered trademarks of Four Js Development
Tools Europe Ltd.

Intel, Itanium, and Pentium are trademarks or registered trademarks of
Intel Corporation or its subsidiaries in the United States and other
countries.

Java(TM) and all Java-based trademarks and logos are trademarks or
registered trademarks of Oracle and/or its affiliates.

Linux is a registered trademark of Linus Torvalds in the United States,
other countries, or both.

Microsoft, Windows, and Windows NT are trademarks of Microsoft
Corporation in the United States, other countries, or both.

UNIX is a registered trademark of The Open Group in the United States
and other countries.

Other company, product, or service names may be trademarks or service
marks of others.

Contact support: http://www.ibm.com/support/entry/portal

© Copyright IBM Corp. 1996, 2013

© Copyright The PHP Group 1997-2010. See the license/notices.txt file
for additional information.

