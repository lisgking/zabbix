<?xml version="1.0" encoding="ISO-8859-1"?>
<xsl:stylesheet version="1.1" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

  <xsl:output method="html" /> 
  <xsl:template match="/">
	
    <html>
      <head>
        <title>XML Feed</title>
        <link rel="stylesheet" href="templates/style.css" type="text/css"/>
      </head>	
      <xsl:apply-templates select="rss/channel"/>		
    </html>

  </xsl:template>

  <xsl:template match="channel">
    <body>	
        <div id="logo">
          <table width="100%">
          <tr>
            <td width="70%">
            <img src='images/logo.gif' border='0' alt='LOGO' />
            </td>
            <td></td>
          </tr>
          </table>
        </div>
        <ul>

	This is an RSS feed from the <a href="/idsadmin/">OAT</a> website. You can use these feeds to access up to date IDS status information from your browser toolbar.
        <br/><br/>
	To subscribe to this feed with a Firefox browser go back to the main RSS page and click on the RSS icon in the URL window at the top of your browser to select a feed to add.
        <br/><br/><br/>
        <table border="1"><tr><td>
        <xsl:apply-templates select="item"/>
        </td></tr></table>
        </ul>
        </body>
   </xsl:template>

  <xsl:template match="item">

    <ul>
      <a href="{link}" class="item"><xsl:value-of select="title"/></a>
        <br/> 
        <div><xsl:value-of select="description" /></div>
    </ul>

   </xsl:template>
		
</xsl:stylesheet>