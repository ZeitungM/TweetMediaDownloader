<?php

  class Tweet
  {
    public static $_curl;
    public static $_curl_header;

    public static $_endpoint_get2tweet = 'https://api.twitter.com/2/tweets';
    public static $_query_tweet_id = '?ids=';
    public static $_queryname_expansions = '&expansions=';
    public static $_querytype_author_id = 'author_id';
    public static $_query_user_fields = 'user.fields=username';

    public static $_query_media_fields = '&expansions=attachments.media_keys&media.fields=url';
    public static $_querytype_attachments_media_keys = 'attachments.media_keys';
    public static $_queryname_media_fields = 'media.fields=';
    public static $_querytype_url = 'url';

    public $_id;
    public $_author;
    public $_selected_images = [];

    function __construct()
    {
        self::$_curl = curl_init();
        curl_setopt( self::$_curl, CURLOPT_CUSTOMREQUEST, 'GET' );
        self::$_curl_header  = array("Authorization: Bearer ".getenv('TWITTER_BEARER_TOKEN'));
        curl_setopt( self::$_curl, CURLOPT_HTTPHEADER, self::$_curl_header );
        curl_setopt( self::$_curl, CURLOPT_RETURNTRANSFER, true );
    }

    function GetTweetIdByRegEx($line)
    {
      // ツイートのURLは以下の形式で与えられるので、 /status/ から最後までか、 ?までを抜き出す
      //https://twitter.com/fox_possession/status/1281219991044386816?s=19　（画像1枚)
      //https://twitter.com/fox_possession/status/1407707096443883527 (画像4枚)
      //https://twitter.com/i/status/1448638849874202628 (動画)
      preg_match('/(?<=\/status\/)\d+/', $line, $match);
      $this->_id = utf8_encode($match[0]);
    }

    function GetSelectedImagesByRegEx($line)
    {
      preg_match_all('/(?<=,)\d+/', $line, $match);
      $selected_images_str = array_map( 'utf8_encode', $match[0]);
      $this->_selected_images = array_map( 'intval', $selected_images_str);
      //var_dump($this->_selected_images);
    }

    function GetUsernameByAPI()
    {
      // ユーザ情報を取得する API : curl "https://api.twitter.com/2/tweets?ids=<tweet_id>&expansions=author_id&tweet.fields=created_at&user.fields=username"  -H "Authorization: Bearer AAAAAAAAAAAAAAAAAAAAABEoMAEAAAAACwO9DW%2F291bM4Cf3G59bUsySSLE%3DBPvqyMMrVoQ5iy1bqsK27dMdRXh8WboAsyBsfgP8pfBqa5RiJX"

      // user.fieldの username の取得には、expansionsの指定も必要
      //$_endpoint_get2tweet =
      //           'https://api.twitter.com/2/tweets';
      // ツイートの URL を curl に設定
      $api_url = self::$_endpoint_get2tweet.self::$_query_tweet_id.$this->_id.self::$_queryname_expansions.self::$_querytype_author_id.'&'.self::$_query_user_fields;
      curl_setopt( self::$_curl, CURLOPT_URL, $api_url);
      $curl_result = curl_exec(self::$_curl);
      $curl_result_utf8 = utf8_encode($curl_result);
      $json_array = json_decode( $curl_result_utf8, true );

      $this->_author = $json_array["includes"]["users"][0]["username"];
      echo "author: ".$this->_author."\n";
    }

    // URL にある画像を、与えられたファイル名に拡張子をつけて保存する
    function Download1Photo( $image_url, $image_stem_name)
    {
      $image_extension = strtolower( pathinfo($image_url)['extension']);
      $image_complete_name = $image_stem_name.".".$image_extension;
      echo "Start to save Image: ", $image_complete_name ,"\n";

      echo "Download Images:download"."\n";
      // URL から画像をダウンロードする
      $image_data = file_get_contents($image_url."?name=orig");
      // TODO: getenv のところ分けられるんじゃない？
      file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_complete_name, $image_data);
      if(!$result)
        echo "Photo Saved: ".$image_complete_name."\n";
      else
        echo "Save Failed: ".$image_complete_name."\n";
    }

    function DownloadPhoto($json_array)
    {
      // 画像の URL と拡張子を取得し、画像のファイル名を作る
      // 画像が複数添付されている場合、['includes']['media'][N]['url'] のNをループすればOK

      if( count($json_array['includes']['media']) >= 2 )
      {
          $image_i = 0;
          $selected_image_i = 0;

          foreach($this->_selected_images as $image_i)
          {
            //  $this->_selected_images[] にある番号の画像をダウンロード
              $this->Download1Photo( $json_array['includes']['media'][$image_i - 1]['url'], $this->_author.".".$this->_id.".".$image_i);
          }

      }
      else // 画像が1枚しかない場合
        $this->Download1Photo( $json_array['includes']['media']['0']['url'], $this->_author.".".$this->_id);
    }

    function DownloadVideo($json_array)
    {
      // 2022/06/04 現在、動画は API2 では URL を取れない
      $tweet_url = 'https://api.twitter.com/1.1/statuses/show.json?id='.$this->_id;
      curl_setopt( self::$_curl, CURLOPT_URL, $tweet_url);

      // curl を実行し、 JSON 形式に変換
      $curl_result = curl_exec(self::$_curl);
      $curl_result_utf8 = utf8_encode($curl_result);
      $json_array = json_decode( $curl_result_utf8, true );

      //$this->PrintApiResult($json_array);

      $mp4_bitrate = 0;
      $index = 0;
      $i = 0;

      // ビットレートが最大のファイルを探す
      foreach( $json_array['extended_entities']['media']['0']['video_info']['variants'] as $movie_media_i )
      {

        if($movie_media_i['content_type']==="video/mp4" && $movie_media_i['bitrate'] > $mp4_bitrate)
        {
          $mp4_bitrate = $movie_media_i['bitrate'];
          $index = $i;
        }

        $i++;
      }

      $movie_url = $json_array['extended_entities']['media']['0']['video_info']['variants'][$index]['url'];
      $movie_info = pathinfo($movie_url);
      $movie_extension = "mp4";
      $movie_name = $json_array['user']['screen_name'].".".$json_array['id'].".".$movie_extension;
      echo "Start to save Movie: ", $movie_name ,"\n";

      $movie_data = file_get_contents($movie_url);
      file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$movie_name, $movie_data);
      if(!$result)
        echo "Movie Saved: ".$movie_name."\n";
      else
        echo "Save Failed: ".$movie_name."\n";
    }

    function DownloadMedia()
    {
      // ツイートのメディア情報の取得
      // curl に URL を設定する
      $tweet_id = str_replace( PHP_EOL, '', $this->_id );
      //$_query_media_fields = '&expansions=attachments.media_keys&media.fields=url';
      $get_image_url = self::$_endpoint_get2tweet.self::$_query_tweet_id.$this->_id.self::$_queryname_expansions.self::$_querytype_attachments_media_keys.'&'.self::$_queryname_media_fields.self::$_querytype_url;
      curl_setopt( self::$_curl, CURLOPT_URL, $get_image_url);

      // curl を実行し、 JSON 形式に変換
      $curl_result = curl_exec(self::$_curl);
      $curl_result_utf8 = utf8_encode($curl_result);
      $json_array = json_decode( $curl_result_utf8, true );
      //$this->PrintApiResult($json_array);

      // エラーなら詳報を表示して終了
      if( array_key_exists( 'errors', $json_array) )
      {
        echo "Error: ".$json_array['errors']['0']['detail']."\n";
        return ;
      }

      // メディアタイプによる分岐
      if( $json_array['includes']['media']['0']['type']=="video" )
      {
        echo "this tweet contains a video\n";
        $this->DownloadVideo($json_array);
        return ;
      }
      elseif( $json_array['includes']['media']['0']['type']=="photo" )
      {
        //$this->PrintApiResult($json_array);
        $this->DownloadPhoto($json_array);
        return ;
      }
      else {
        echo "this tweet contains unknown type media:(".$json_array['includes']['media']['0']['type'].")\n";
        return ;
      }

    }

    function PrintApiResult($array)
    {
      var_dump($array);
    }

  }

    // ここから
    // URL.txt から一行ずつ読み込み
    $file_path = getenv('TWITTER_URL_FILE');
    $file_handle = fopen( $file_path, 'r' );

    $screen_name;
    $tweets = [];

    $tweet_ids = [];
    $selected_images = [];
    $i = 0;
    $tweet_i = 0;
    while( $tweet_url = fgets($file_handle) )
    {

        $tweet = new Tweet();

        // URL から ツイートのIDを取得
        $tweet->GetTweetIdByRegEx($tweet_url);

        $tweet->GetSelectedImagesByRegEx($tweet_url);

        $tweet->GetUsernameByAPI();

          // URL から選択された画像の番号を取得
        $tweet->GetSelectedImagesByRegEx($tweet_url);

        $i++;
        // これから処理する URL を "読み込んだURLの番号: URL" の形式で表示
        echo $i, ": ", str_replace( array("\r\n", "\r", "\n"), '', $tweet_url), "\n";

        $tweet->DownloadMedia();
    }

    curl_close($_curl);
    fclose($file_handle);
?>
