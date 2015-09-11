<?php

Class News
{
    public static $DBConnection;
    public static $CConnection;
    public static $TM;

    public function __construct($VariablesArray)
    {
        News::$DBConnection = $VariablesArray[0]::$Connection;
        News::$CConnection = $VariablesArray[0]::$CConnection;
        News::$TM = $VariablesArray[1];
    }

    public static function GetAllNews()
    {
        $Statement = News::$DBConnection->prepare('SELECT * FROM news ORDER BY post_date DESC');
        $Statement->execute();
        $Result = $Statement->fetchAll(PDO::FETCH_ASSOC);
        $Index = 0;
        foreach($Result as $Article)
        {
            $CountStatement = News::$DBConnection->prepare('SELECT count(id) as count FROM comments WHERE article_id = :aid');
            $CountStatement->bindParam(':aid', $Article['id']);
            $CountStatement->execute();
            $CountResult = $CountStatement->fetch(PDO::FETCH_ASSOC);
            $Result[$Index]['comments_count'] = $CountResult['count'];
            $Index++;
        }
        return $Result;
    }

    public static function GetArticle($ArticleID)
    {
        $Statement = News::$DBConnection->prepare('SELECT *, (SELECT count(id) FROM comments WHERE article_id = :articleid) as comments_count FROM news WHERE id = :articleid');
        $Statement->bindParam(':articleid', $ArticleID);
        $Statement->execute();
        $Result = $Statement->fetch(PDO::FETCH_ASSOC);
        if(Text::IsNull($Result['id']))
            return null;
        else
            return $Result;
    }

    public static function GetComments($ArticleID)
    {
        global $FCCore;
        $Statement = News::$CConnection->prepare('
            SELECT
                c.*,
                ch.race as poster_race,
                ch.class as poster_class,
                ch.gender as poster_gender
            FROM
                '.$FCCore['Database']['database'].'.comments c, characters ch
            WHERE
                    c.comment_by = ch.name
                AND
                    article_id = :articleid
                AND
                    replied_to IS NULL
            ORDER BY
              id
            DESC');
        $Statement->bindParam(':articleid', $ArticleID);
        $Statement->execute();
        $Result = $Statement->fetchAll(PDO::FETCH_ASSOC);
        $ArrayIndex = 0;
        foreach($Result as $Comment)
        {
            $Result[$ArrayIndex]['nested_comments'] = News::GetNestedComments($Comment['article_id'], $Comment['id']);
            $ArrayIndex++;
        }
        return $Result;
    }

    private static function GetNestedComments($ArticleID, $ParentCommendID)
    {
        global $FCCore;
        $Statement = News::$CConnection->prepare('
            SELECT
                c.*,
                ch.race as poster_race,
                ch.class as poster_class,
                ch.gender as poster_gender
            FROM
                '.$FCCore['Database']['database'].'.comments c, characters ch
            WHERE
                c.comment_by = ch.name
            AND
                replied_to = :rt
            AND
                article_id = :aid

        ');
        $Statement->bindParam(':rt', $ParentCommendID);
        $Statement->bindParam(':aid', $ArticleID);
        $Statement->execute();
        $Result = $Statement->fetchAll(PDO::FETCH_ASSOC);
        return $Result;
    }

    public static function AddComment($ArticleID, $PostedBy, $CommentText)
    {
        $DateAndTime = date('Y-m-d H:i:s');
        $Statement = News::$DBConnection->prepare('INSERT INTO comments (article_id, comment_by, comment_text, comment_date) VALUES (:articleid, :poster, :comment, :dt)');
        $Statement->bindParam(':articleid', $ArticleID);
        $Statement->bindParam(':poster', $PostedBy);
        $Statement->bindParam(':comment', $CommentText);
        $Statement->bindParam(':dt', $DateAndTime);
        $Statement->execute();
    }

    public static function AddReply($ArticleID, $PostedBy, $CommentText, $ReplyTo)
    {
        $DateAndTime = date('Y-m-d H:i:s');
        $Statement = News::$DBConnection->prepare('INSERT INTO comments (article_id, comment_by, comment_text, comment_date, replied_to) VALUES (:articleid, :poster, :comment, :dt, :rt)');
        $Statement->bindParam(':articleid', $ArticleID);
        $Statement->bindParam(':poster', $PostedBy);
        $Statement->bindParam(':comment', $CommentText);
        $Statement->bindParam(':dt', $DateAndTime);
        $Statement->bindParam(':rt', $ReplyTo);
        $Statement->execute();
    }

    public static function GetLastCommentID($ArticleID)
    {
        $Statement = News::$DBConnection->prepare('SELECT max(id) as maxcomment FROM comments WHERE article_id = :articleid');
        $Statement->bindParam(':articleid', $ArticleID);
        $Statement->execute();
        $Result = $Statement->fetch(PDO::FETCH_ASSOC);
        return $Result['maxcomment'];
    }
}

?>