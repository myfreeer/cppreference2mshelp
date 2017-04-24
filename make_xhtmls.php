<?

ini_set('memory_limit', '500M');
gc_enable();

$time_start = microtime(true);

////////////
if ( count($argv) < 2 )
{
	echo('no arguments: c++ amd c documentations will be built'."\n");

	// build cpp documentation
	buildXHtmls(true);

	// build c documentation
	buildXHtmls(false);
}
else if ( $argv[1] == 'c')
{
	echo('c documentation will be built'."\n");
	// build c documentation
	buildXHtmls(false);
}
else
{
	echo('c++ documentation will be built'."\n");
	// build cpp documentation
	buildXHtmls(true);
}

////////////

$time_end = microtime(true);
$time = $time_end - $time_start;
echo( "\nbuild time: ".  $time." seconds.\n");


function buildXHtmls( $cpp = true )
{
	$scriptDir = dirname( __FILE__ );
	if ( $cpp )
	{
		$targetDir = $scriptDir.DIRECTORY_SEPARATOR ."cpphelp";
	}
	else
	{
		$targetDir = $scriptDir.DIRECTORY_SEPARATOR ."chelp";
	}

	if ( file_exists( $targetDir ) == false )
	{
		if ( mkdir( $targetDir ) == false )
		{
			echo("can't create output directory: ". $targetDir."\n");
		}
	}

	$sourceDir = $scriptDir.DIRECTORY_SEPARATOR ."en";	
	$commonDir = $scriptDir.DIRECTORY_SEPARATOR ."common";	
	$cssDir = $scriptDir.DIRECTORY_SEPARATOR ."css";
	
	$errorsFiles = array();
	$arrFiles = array();
	dirToArray( $arrFiles, $sourceDir, ".html" );

	echo( "files: ".count( $arrFiles )."\n");

	$processedFiles = 0;
	$noTitleLinks = 0;
	$colspans = 0;

	foreach( $arrFiles as $file ) 
	{
		$filelow = strtolower( $file );
		$relativename = substr( $filelow, strlen( $sourceDir ) + 1 );	

		// current file
		echo( $relativename."\n" );

		$targetfile = $targetDir.DIRECTORY_SEPARATOR;
		$targetfile .= str_replace( DIRECTORY_SEPARATOR , "-", $relativename );
		$id = str_replace( ".html", "", $relativename );

		// detecting right documentation and skip unwanted
		$isCpp = false;
		if ( strlen( $id ) > 1 && $id[1] == 'p' )
		{
			$isCpp = true;
		}

		if ( $cpp == true && $isCpp == false )
		{
			// skip c documentation
			continue;
		}

		if ( $cpp == false && $isCpp == true )
		{
			// skip cpp documentation
			continue;
		}

		// start processing
		$processedFiles++;

		$id = str_replace( "\\", "/", $id );
		$parent_id = "";
		$patharr = explode( "/", $id );
		if ( count( $patharr ) > 1 )
		{
			array_pop( $patharr );
			foreach( $patharr as $pathelem ) 
			{
				if ( strlen($parent_id) > 0 )
				{
					$parent_id .= "/".$pathelem;
				}
				else
				{
					$parent_id = $pathelem;
				}
			}			
		}

		// document id and parent document id
		$id = str_replace( "/", "-", $id );
		$parent_id = str_replace( "/", "-", $parent_id );
		
		// fix xhtml errors
		exec("tidy -config config.txt -o ".$targetfile." ".$file );

		// load xhtml and fix tags
		$dom = new DomDocument('1.0', 'UTF-8');
		
		$dom->loadHTMLFile( $targetfile );

		// delete scripts
		deleteNodes( $dom );

		// fix links to styles
		$nodes = getNodesByName( $dom, "link");
		foreach( $nodes as $e )
		{
			if( $e->hasAttribute("href")) 
			{
				$e->setAttribute("href", basename( $e->getAttribute("href")));
			}

			if( $e->getAttribute("rel") != "stylesheet" ) 
			{
				$e->parentNode->removeChild( $e );
			}
		}

		// remove divs with internal info > 1mb
		$nodes = getNodesByName( $dom, "div");
		foreach( $nodes as $e ) 
		{
			if( $e->getAttribute("class") == "printfooter" || strpos( $e->getAttribute("class"), "catlinks" ) !== false || strpos( $e->getAttribute("class"), "coliru-btn" ) !== false )
			{
				$e->parentNode->removeChild( $e );
			}
			else if ( $e->getAttribute('id') == "siteSub" || $e->getAttribute('id') == "mw-js-message")
			{
				$e->parentNode->removeChild( $e );
			}

			// remove navbar?
			if ($e->getAttribute("class") == "t-navbar")
			{
				//$e->parentNode->removeChild( $e );
			}
		}
		$nodes = getNodesByName( $dom, "style");
		foreach( $nodes as $e ) 
		{
			if( strpos( $e->textContent, "a:lang" ) !== false )
			{
				$e->parentNode->removeChild( $e );
			}
			else
			{
				$e->textContent = removeCommentFromCss( $e->textContent );
			}
		}


		// fix links to other pages
		$nodes = array_merge( getNodesByName( $dom, "a"), getNodesByName( $dom, "area"));
		foreach( $nodes as $e ) 
		{
			if( $e->hasAttribute("title"))
			{
				$href = $e->getAttribute("href");
				if ( strpos( $href, "http") === false )
				{
					$new_value = str_replace( "/" , "-", $e->getAttribute("title"));					
					// fix bad titles where space is used instead of _
					$new_value = str_replace( " " , "_", $new_value);

					$e->setAttribute("href", "ms-xhelp:///?method=page&id=".$new_value );

					// remove title from links?
					$e->removeAttribute("title");
				}
			}
			else if( $e->hasAttribute("href"))
			{
				$href = $e->getAttribute("href");
				if ( strpos( $href, "http") === false )
				{
					// bad link
					$noTitleLinks++;
					$relativenamefix = str_replace( DIRECTORY_SEPARATOR, "/", $relativename );
					$relative_folders = explode( "/" , $relativenamefix );
					$unFolders = substr_count( $href , "../" );
					$hrefname = str_replace( "../", "", $href );
					$hrefname = str_replace( ".html", "", $hrefname );

					$unFolders++;
					for ( $ind = 0; $ind < $unFolders; $ind++)
					{
						array_pop( $relative_folders );
					}

					$fref_folders = explode( "/" , $hrefname );

					foreach ($fref_folders as $frf) 
					{
						$relative_folders[] = $frf;
					}

					$fixedhref = implode( "-", $relative_folders );
					$e->setAttribute("href", "ms-xhelp:///?method=page&id=".$fixedhref );

					/*
					$errorsFiles[] = $href;
					$errorsFiles[] = $relativenamefix;
					$errorsFiles[] = $fixedhref;
					$errorsFiles[] = " ";

					if ( $noTitleLinks > 5 )
					{

					 	echo( "links without title: ".$noTitleLinks."\n");

						foreach ($errorsFiles as $fl)
						{
							echo( $fl . "\n");
						}
						return;
					}
					*/
				}
			}
		}

		// remove unwanted colspan="5", about 4mb of text!
		$nodes = getNodesByName( $dom, "td");
		foreach( $nodes as $e ) 
		{
			if( $e->hasAttribute("colspan") && $e->getAttribute("colspan") == 5 )
			{
				$e->removeAttribute("colspan");
				$colspans++;
			}
		}
		
		// fix links to images
		$nodes = getNodesByName( $dom, "img");
		foreach( $nodes as $e ) 
		{
			if( $e->hasAttribute("src"))
			{
				$e->setAttribute("src", basename( $e->getAttribute("src")));
			}
		}

		// getting and fixing document title remplace <h1> to <div class="title">
		$document_title = "";
		$h1title = $dom->getElementById("firstHeading");
		if ( $h1title )
		{
			foreach( $h1title->childNodes as $h1n ) 
			{
				$document_title .= $h1n->textContent;
			}

			$new_div = $dom->createElement('div');
			$new_attr = $dom->createAttribute('class');
			$new_attr->value = 'title';
			$new_div->appendChild($new_attr);
			$new_text = $dom->createTextNode( $document_title );
			$new_div->appendChild($new_text);
			$h1title->parentNode->replaceChild($new_div, $h1title );

			$nodes = getNodesByName( $dom, "title");
			foreach( $nodes as $e ) 
			{
				$new_text = $dom->createTextNode( $document_title );
				$e->replaceChild( $new_text, $e->firstChild );
			}			
		}
		else
		{
			// no page title
			$errorsFiles[] = $targetfile;
		}
		// current file info
		echo("id: ".$id." parent id: ".$parent_id." title: ".$document_title."\n");

		/*
	    <meta name="Microsoft.Help.Id" content="DABD1A5D-DDE1-472D-9DCA-74A886B2CCC6" />
	    <meta name="SelfBranded" content="false" />
	    <meta name="Microsoft.Help.Locale" content="en-us" />
	    <meta name="Microsoft.Help.TocParent" content="A090A59F-A8FA-489F-A600-9E7BFB67E5AD" />
	    <meta name="Microsoft.Help.Category" content="DevLang:C++" />
	    <meta name="Microsoft.Help.Category" content="DevLang:C" />	    
   	    <meta name="Title" content="..." />

	    <meta name="Microsoft.Help.Package" content="v2API__apps...en-us_2" />
	    <meta name="Microsoft.Help.Book" content="API Reference for ..." />
	    <meta name="Description" content=" . . ." />	    
		*/

		// adding new nodes
		$head = getNodesByName( $dom, "head");
		if ( count( $head ) == 1 )
		{
			// <meta name="Title" content="..." />
			addNewMeta( $dom, $head[0], 'Title', $document_title );

			// <meta name="Microsoft.Help.Id" content="DABD1A5D-DDE1-472D-9DCA-74A886B2CCC6" />
			addNewMeta( $dom, $head[0], 'Microsoft.Help.Id', $id );

			// <meta name="SelfBranded" content="false" />
			addNewMeta( $dom, $head[0], 'SelfBranded', 'false' );

			// <meta name="Microsoft.Help.Locale" content="en-us" />
			addNewMeta( $dom, $head[0], 'Microsoft.Help.Locale', 'en-us' );

			// <meta name="Microsoft.Help.TocParent" content="A090A59F-A8FA-489F-A600-9E7BFB67E5AD" />
			addNewMeta( $dom, $head[0], 'Microsoft.Help.TocParent', $parent_id );

			// <meta name="source" content="http://www.cppreference.com" />
			//addNewMeta( $dom, $head[0], '@source', 'http://www.cppreference.com' );

			// <meta name="converted by" content="CEZEO software Ltd." />
			//addNewMeta( $dom, $head[0], '@converted', 'http://www.cezeo.com' );

			// <meta name="Microsoft.Help.Keywords" content="[aKeywordPhrase]"/>			
			$keywords = array();

			$pre_keywords = explode( ",", htmlspecialchars_decode( $document_title ));
			foreach ( $pre_keywords as $keywrd )
			{
				if ( strpos( $keywrd, "std::" ) !== false )
				{
					$post_keywords = explode( "::", $keywrd);
					$keywords[] = $post_keywords[ count( $post_keywords ) - 1 ];

				}
				else if ( strpos( $keywrd, ":" ) !== false )
				{
					$post_keywords = explode( ":", $keywrd);
					$keywords[] = $post_keywords[ count( $post_keywords ) - 1 ];
				}
				else
				{
					$keywords[] = $keywrd;
				}
			}
			if ( count( $keywords ) == 0 )
			{
				$keywords = $pre_keywords;
			}

			while ( count( $keywords ) > 0 )
			{
				$kwrd = array_pop( $keywords );
				addNewMeta( $dom, $head[0], 'Microsoft.Help.Keywords', $kwrd);
				addNewMeta( $dom, $head[0], 'Microsoft.Help.F1', $kwrd );
			};


			if ( strlen( $id ) > 1 && $id[1] == 'p' )
			{
				// <meta name="Microsoft.Help.Category" content="DevLang:C++" />
				addNewMeta( $dom, $head[0], 'Microsoft.Help.Category', 'DevLang:C++' );
				// <meta name="Microsoft.Help.TocRoot" content="hh703192 (en-us;Win.10)" />
				addNewMeta( $dom, $head[0], 'Microsoft.Help.TocRoot', 'cpp' );
			}
			else
			{
				// <meta name="Microsoft.Help.Category" content="DevLang:C" />	
				addNewMeta( $dom, $head[0], 'Microsoft.Help.Category', 'DevLang:C' );
				// <meta name="Microsoft.Help.TocRoot" content="hh703192 (en-us;Win.10)" />
				addNewMeta( $dom, $head[0], 'Microsoft.Help.TocRoot', 'c' );
			}			
		}
		else
		{
			// no head or more than one
			$errorsFiles[] = $targetfile;
		}

		// Save processed file
		$dom->saveHTMLFile( $targetfile );
		exec("tidy -config config.txt -o ".$targetfile." ".$targetfile );

		if ( $processedFiles > 4 )
		{
			//break;
		}
		gc_collect_cycles();
	}
	echo ("total files processed: ".$processedFiles ." errors: ".count($errorsFiles)."\n" );

	

	// copying images etc.
	$arrFiles = array();
	dirToArray( $arrFiles, $commonDir, "." );
	echo( "copying images and styles ...\n");
	echo( "files: ".count( $arrFiles )."\n");
	foreach( $arrFiles as $file ) 
	{
		if ( endsWith( $file, '.css' ))
		{
			copy( $file, $targetDir.DIRECTORY_SEPARATOR.basename( $file ));
			echo( "file copied: ".basename( $file )."\n");
		}
		else if ( endsWith( $file, '.png' ))
		{
			copy( $file, $targetDir.DIRECTORY_SEPARATOR.basename( $file ));
			echo( "file copied: ".basename( $file )."\n");
		}
		else if ( endsWith( $file, '.svg' ))
		{
			copy( $file, $targetDir.DIRECTORY_SEPARATOR.basename( $file ));
			echo( "file copied: ".basename( $file )."\n");
		}
		gc_collect_cycles();
	}

	// replace css filse
	$arrFiles = array();
	dirToArray( $arrFiles, $cssDir, ".css" );
	echo( "copying new styles ...\n");
	echo( "files: ".count( $arrFiles )."\n");
	foreach( $arrFiles as $file ) 
	{
		copy( $file, $targetDir.DIRECTORY_SEPARATOR.basename( $file ));
		echo( "file copied: ".basename( $file )."\n");
		gc_collect_cycles();
	}

	// finishing
	// building metadata.xml
	$arrFiles = array();
	dirToArray( $arrFiles, $targetDir, "." );
	echo( "building metadata.xml ...\n");
	echo( "files: ".count( $arrFiles )."\n");

	$metadata = '<?xml version="1.0" encoding="utf-8"?><metadata package-name="cppreference" xmlns="urn:MTPS-FH:metadata"><locale ref="" /><items>'."\n";

	foreach( $arrFiles as $file ) 
	{		
		if ( endsWith( $file, '.html' ))
		{
			$metadata .= '<item name="'.basename( $file ).'" content-type="text/html; charset=utf-8"/>'."\n";
		}
		else if ( endsWith( $file, '.css' ))
		{
			$metadata .= '<item name="'.basename( $file ).'" content-type="text/css"/>'."\n";
		}
		else if ( endsWith( $file, '.png' ))
		{
			$metadata .= '<item name="'.basename( $file ).'" content-type="image/png"/>'."\n";
		}		
		else if ( endsWith( $file, '.svg' ))
		{
			$metadata .= '<item name="'.basename( $file ).'" content-type="image/svg+xml"/>'."\n";
		}
		gc_collect_cycles();
	}	

 	$metadata .= '</items><asset-ids /></metadata>';
 	file_put_contents( $targetDir.DIRECTORY_SEPARATOR.'metadata.xml', $metadata );


}

/*
cpp
htmls: 3863
files: 3941
time: 145334
withnavbar:    117724392 bytes
withoutnavbar:  48978266 bytes

c
htmls: 552
files: 642
time: 19132
*/

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0)
    {
        return true;
    }
    return (substr($haystack, -$length) === $needle);
}

function addNewMeta( $dom, $head, $name, $content )
{
	$new_meta = $dom->createElement('meta');
	$new_attr = $dom->createAttribute('name');
	$new_attr->value = $name;
	$new_meta->appendChild($new_attr);
	$new_attr = $dom->createAttribute('content');
	$new_attr->value = $content;
	$new_meta->appendChild($new_attr);
	$head->appendChild($new_meta);
}

function getNodesByName( $nde, $name )
{
	$elems = array();
	if ( $nde->childNodes )
	{
	   	foreach ($nde->childNodes as $nd)
		{
	       	$elems = array_merge( getNodesByName( $nd, $name ), $elems);
	 	}
	}

	if ( $nde->nodeName === $name)
	{
		$elems[] = $nde;
	}
	return $elems;
}

function deleteNodes( $nde )
{
	if ( $nde->childNodes )
	{
		$toRemove = array();
	   	foreach ($nde->childNodes as $nd)
		{
			deleteNodes( $nd );
			if ( deleteNode((string)$nd->nodeName ))
			{
				$toRemove[] = $nd;
			}
		}
		foreach ($toRemove as $remNd)
		{
			$nde->removeChild( $remNd );
		}
	}
}

function deleteNode( $nodeName )
{
	if ( $nodeName == "script" || $nodeName == "meta" )
	{
		return true;
	}
	return false;
}

function removeCommentFromCss( $textContent )
{
	$clearText = "";
	$charsInCss = strlen( $textContent );
	$searchForStart = true;
	for( $index = 0; $index < $charsInCss; $index++ )
	{
		if ( $searchForStart )
		{
			if ( $textContent[ $index ] == "/" && (( $index + 1 ) < $charsInCss ) && $textContent[ $index + 1 ] == "*" )
			{
				$searchForStart = false;
				continue;
			}
			else
			{
				$clearText .= $textContent[ $index ];
			}
		}
		else
		{
			if ( $textContent[ $index ] == "*" && (( $index + 1 ) < $charsInCss ) && $textContent[ $index + 1 ] == "/" )
			{
				$searchForStart = true;
				$index++;
				continue;
			}
		}
	}
	return $clearText;
}


function dirToArray(&$files, $dir, $ext)
{    
   $cdir = scandir($dir); 
   foreach ($cdir as $key => $value) 
   { 
      if (!in_array($value,array(".",".."))) 
      { 
         if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) 
         { 
            dirToArray($files, $dir . DIRECTORY_SEPARATOR . $value, $ext);
         } 
         else 
         { 
         	if ( strpos( $value, $ext ) !== false )
         	{
         		if ( strpos( $value, "\\" ) === 0 )
         		{
         	 	  $files[] = $dir.$value;
         		}
         		else
         		{
         			$files[] = $dir."\\".$value;	
         		}
        	}
         } 
      } 
   } 
} 

?>