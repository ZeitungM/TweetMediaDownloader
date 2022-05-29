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
    public $_selected_images;

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
      //https://twitter.com/mt_fujimaru/status/1352192965704204291?s=19
      //https://twitter.com/warecommon/status/1354746039492878336
      //https://twitter.com/i/web/status/1354498563091390464
      preg_match('/(?<=\/status\/)\d+/', $line, $match);
      $this->_id = utf8_encode($match[0]);
    }

    function GetSelectedImagesByRegEx($line)
    {
      preg_match_all('/(?<=,)\d+/', $line, $match);
      $this->_selected_images = array_map( 'utf8_encode', $match[0]);
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

    function DownloadPhoto($json_array)
    {
      // 画像の URL と拡張子を取得し、画像のファイル名を作る
      // 画像が複数添付されている場合、['includes']['media'][N]['url'] のNをループすればOK
      
      if( count($json_array['includes']['media']) >= 2 )
      {
          $image_i = 1;
          foreach($image_url = $json_array['includes']['media'] as $photo_i )
          {
              // TODO: ここをわけたい
              $image_url = $photo_i['url'];
              $image_extension = strtolower( pathinfo($image_url)['extension']);
              $image_name = $this->_author.".".$this->_id.".".$image_i.".".$image_extension;
              echo "Start to save Image: ", $image_name ,"\n";

              echo "Download Images:download"."\n";
              // URL から画像をダウンロードする
              $image_data = file_get_contents($image_url."?name=orig");
              file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_name, $image_data);
              if(!$result)
                echo "Photo Saved: ".$image_name."\n";
              else
                echo "Save Failed: ".$image_name."\n";
              $image_i++;
          }
      }
      else {
        $image_url = $json_array['includes']['media']['0']['url'];
        $image_extension = strtolower( pathinfo($image_url)['extension']);
        $image_name = $this->_author.".".$this->_id.".".$image_extension;
        echo "Start to save Image: ", $image_name ,"\n";

        echo "Download Images:download"."\n";
        // URL から画像をダウンロードする
        $image_data = file_get_contents($image_url."?name=orig");
        file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_name, $image_data);
        if(!$result)
          echo "Photo Saved: ".$image_name."\n";
        else
          echo "Save Failed: ".$image_name."\n";
      }
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

      // メディアタイプによる分岐
      if( $json_array['includes']['media']['0']['type']=="video" )
      {
        echo "this tweet contains a video\n";
        return ;
      }
      elseif( $json_array['includes']['media']['0']['type']=="photo" )
      {
          $this->DownloadPhoto($json_array);
      }
      else {
        echo "this tweet contains unknown type media\n";
        return ;
      }

    }

    function PrintApiResult($array)
    {
      var_dump($array);
    }

    //public function
  }

    function DownloadImageFromTweetId( $tweet_id, $screen_name, $selected_images, $curl)
    {
        // curl に URL を設定する
        $tweet_id = str_replace( PHP_EOL, '', $tweet_id );
        //$tweet_url = 'https://api.twitter.com/1.1/statuses/show.json?id='.$tweet_id;
        $tweet_url = 'https://api.twitter.com/2/tweets/'.$tweet_id.'?expansions=attachments.media_keys&media.fields=url';
        curl_setopt( $curl, CURLOPT_URL, $tweet_url);

        // curl を実行し、 JSON 形式に変換
        $curl_result = curl_exec($curl);
        $curl_result_utf8 = utf8_encode($curl_result);
        $json_array = json_decode( $curl_result_utf8, true );

        var_dump($curl_result);

        //test: ユーザ情報を取得する
        /*
        echo "user info\n";
        $tweet_url = 'https://api.twitter.com/2/tweets/'.$tweet_id.'&expansions=author_id&tweet.fields=created_at&user.fields=username,verified';
        curl_setopt( $curl, CURLOPT_URL, $tweet_url);
        $curl_result = curl_exec($curl);
        $curl_result_utf8 = utf8_encode($curl_result);
        $json_array = json_decode( $curl_result_utf8, true );

        var_dump($curl_result);*/


        if(isset($json_array['errors']))
        {
            echo "Error(".$json_array['errors'][0]['code'].") ".$json_array['errors'][0]['message']."\n";
            echo "\n";
            return;
        }

        //if(!isset($json_array['extended_entities']['media']))
        if(!isset($json_array['includes']['media']))
        {
            echo "Unsupported JSON Type. json['extended_entities']['media'] is not exist.\n";
        }

        // 添付メディアが画像の場合
        //if($json_array['extended_entities']['media']['0']['type']==='photo')
        if($json_array['includes']['media']['0']['type']==='photo')
        {
            $i = 1;
            if( count($json_array['includes']['media']) >= 2 )
            {
              foreach($image_url = $json_array['includes']['media'] as $photo_i )
              {
                  if( empty($selected_images) || in_array( $i, $selected_images ) )
                  {
                    // 画像の URL と拡張子を取得し、画像のファイル名を作る
                    $image_url = $photo_i['media_url_https'];
                    $image_extension = strtolower( pathinfo($image_url)['extension']);
                    $image_name = $json_array['user']['screen_name'].".".$json_array['id'].".".$i.".".$image_extension;
                    echo "Start to save Image: ", $image_name ,"\n";

                    $image_data = file_get_contents($image_url."?name=orig");
                    $result = file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_name, $image_data);
                    if(!$result)
                      echo "Photo Saved: ".$image_name."\n";
                    else
                      echo "Save Failed: ".$image_name."\n";
                  }

                  $i++;
              }
            }
            else
            {
              // 画像の URL と拡張子を取得し、画像のファイル名を作る
              $image_url = $json_array['includes']['media']['0']['url'];
              $image_extension = strtolower( pathinfo($image_url)['extension']);
              $image_name = $screen_name.".".$tweet_id.".".$image_extension;
              echo "Start to save Image: ", $image_name ,"\n";

              $image_data = file_get_contents($image_url."?name=orig");
              file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$image_name, $image_data);
              if(!$result)
                echo "Photo Saved: ".$image_name."\n";
              else
                echo "Save Failed: ".$image_name."\n";
            }

        }
        // 添付メディアが動画の場合
        elseif($json_array['extended_entities']['media']['0']['type']==='video')
        {
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
            echo "Movie Saved: ".$movie_name."\n";
        }
        // gif アニメのとき
        elseif($json_array['extended_entities']['media']['0']['type']==='animated_gif')
        {
            $movie_url = $json_array['extended_entities']['media']['0']['video_info']['variants'][0]['url'];
            $movie_info = pathinfo($movie_url);
            $movie_extension = "mp4";
            $movie_name = $json_array['user']['screen_name'].".".$json_array['id'].".".$movie_extension;
            echo "Start to save animated gif: ", $movie_name ,"\n";

            $movie_data = file_get_contents($movie_url);
            file_put_contents( getenv('TWITTER_DOWNLOAD_DIRECTORY').$movie_name, $movie_data);
            echo "Movie Saved: ".$movie_name."\n";
        }
        echo "\n";
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
    // 行が読み込まれる間繰り返し
    while( $tweet_url = fgets($file_handle) )
    {
      //$tweet = new Tweet($tweet_id);
      //$tweets[] = new  Tweet();
      $tweet = new Tweet();

        // URL から ツイートのIDを取得
        $tweet->GetTweetIdByRegEx($tweet_url);

        $tweet->GetSelectedImagesByRegEx($tweet_url);

        $tweet->GetUsernameByAPI();

        // URL から選択された画像の番号を取得
        //preg_match_all('/(?<=,)\d+/', $tweet_url, $match);
        //$selected_images = array_map( 'utf8_encode', $match[0]);

        $i++;
        // これから処理する URL を "読み込んだURLの番号: URL" の形式で表示
        echo $i, ": ", str_replace( array("\r\n", "\r", "\n"), '', $tweet_url), "\n";
        $tweet->DownloadMedia();

        //DownloadImageFromTweetId( $tweet_id, $screen_name, $selected_images, $curl);
    }

    //curl_close($_curl); // todo: static 化した curl の curl_close をどうするか
    fclose($file_handle);
?>
