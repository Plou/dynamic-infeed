<?php
/**
 * Plugin Name:     Dynamic Infeed
 * Description:     Insert Ads every x words or images
 * Text Domain:     dynamic-infeed
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Dynamic_Infeed
 */

add_filter( 'the_content', 'filter_the_content_in_the_main_loop', 8);

function filter_the_content_in_the_main_loop( $content ) {
  // Check if we're inside the main loop in a single post page.
  if ( is_single() && is_main_query() ) {
    $imageFrequency = 4;
    $wordFrequency = 200;
    if(getImageCount($content) >= $imageFrequency) {
      $content = insertAdAfterImages($content, $imageFrequency);
    }
    else if(getWordCount($content) >= $wordFrequency) {
      $content = insertAdAfterWords($content, $wordFrequency);
    }
  }

  return $content;
}


function getImageCount($content) {
  return preg_match_all('/<(blockquote|iframe|twitterwidget|img)/i', $content);
}

function getWordCount($content) {
  return count(explode(' ', strip_tags($content)));
}

function insertAdAfterWords($content, $frequency) {
  $tags = preg_split('/(<\/?(?!(?:\/?(em|strong|i|a)))[^>]*?[^\/]>)|([\r\n])|&nbsp;/i', $content, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

  $blockquotes = array();
  foreach ($tags as $key => $tag) {
    if (explode(' ', $tag)[0] == '<blockquote' || explode(' ', $tag)[0] == '<blockquote>') {
      $blockquoteStart = $key;
    }
    if ($tag == '</blockquote>') {
      $blockquoteEnd = $key;
      $blockquotes[$blockquoteStart] = $blockquoteEnd;
    }
  }

  $texts = $tags;
  if (count($blockquotes) > 0) {
    foreach ($blockquotes as $start => $end) {
      for ($i=$start; $i <= $end; $i++) {
        unset($texts[$i]);
      }
    }
  }

  $texts = array_filter($texts, function($element) {
    return $element && !preg_match('/(\[[^\]]*[^\/]\])|(<\/?(?!(?:\/?(em|strong|i|a)))[^>]*?[^\/]>)|([\r\n])|&nbsp;/i', $element);
  });


  $texts = array_map(function($element) {
    return strip_tags($element);
  }, $texts);


  $texts = array_filter($texts);


  $totalWord = 0;
  $i = 1;
  foreach ($texts as $key => $text) {
    $totalWord += count(explode(' ', $text));
    if ($totalWord >= $frequency) {
      $totalWord = $totalWord % $frequency;
      array_splice($tags, $key + $i, 0, getAd($i));
      $i++;
    }
  }

  $content = implode($tags);
  return $content;
}
function insertAdAfterImages($content, $frequency) {
  $tags = preg_split('/(\[[^\]]*[^\/]\])|(<[^>]*[^\/]>)|([\r\n])|(<img.*?>)(?!(?:(?!\[caption).)*\[\/caption\])/i', $content, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

  $images = array_filter($tags, function($element) {
    return preg_match('/(<(\/blockquote|\/iframe|\/twitterwidget|img)|\[\/caption)/i', $element);
  });

  $totalImage = 0;
  $i = 1;

  foreach ($images as $key => $image) {
    if(!isset($images[$key+1]) || !$images[$key+1] == '[/caption]') {
      $totalImage++;

      if ($totalImage >= $frequency) {
        $totalImage = $totalImage % $frequency;

        array_splice($tags, $key + $i, 0, getAd($i));
        $i++;
      }
    }
  }

  $content = implode($tags);
  return $content;
}

function getAd($i) {
  return '
    <div id="dynInfeed'. $i .'"></div>
    <script>if (typeof(prebid) !== "undefined" && typeof(prebidFn.defineDynamicAdslot) !== "undefined")
    prebidFn.defineDynamicAdslot("dynInfeed", '. $i .');</script>
  ';
}


?>
