<?php

set_time_limit(0);
error_reporting(1);
ini_set('memory_limit', '-1');
ini_set('mysqli.reconnect', 1);

include_once('library/config.php');
$orchard_dbs = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, 'theorchard_april') or die("Could not connect to the Orchard Database at DB1");
mysqli_set_charset($orchard_dbs, 'UTF8');

for ($i = 0; $i <= 150; $i++)
{
    $track_id_query = "SELECT tracks_new.track_id, theorchard.tracks.upc
                        FROM theorchard_april.tracks_new,theorchard.tracks
                        WHERE 
                        (   LENGTH(theorchard_april.tracks_new.title) != LENGTH(theorchard.tracks.title) 
                            OR LENGTH(theorchard_april.tracks_new.pline) != LENGTH(theorchard.tracks.pline)
                            or LENGTH(theorchard_april.tracks_new.rights_granted) != LENGTH(theorchard.tracks.rights_granted) 
                            OR LENGTH(theorchard_april.tracks_new.explicit_lyrics) != LENGTH(theorchard.tracks.explicit_lyrics)
                            OR LENGTH(theorchard_april.tracks_new.ioda_display_artist_name) != LENGTH(theorchard.tracks.ioda_display_artist_name) )
                        and theorchard_april.tracks_new.track_id = theorchard.tracks.track_id
                        and theorchard_april.tracks_new.isrc = theorchard.tracks.isrc 
                        limit 10000 ";
    $track_id_resource = mysqli_query($orchard_dbs, $track_id_query);

    while ($track = mysqli_fetch_assoc($track_id_resource))
    {
        echo "track_id:" . $track['track_id'] . " :UPC:" . $track['upc'];
        $update_new_query = "Update theorchard.tracks , theorchard_april.tracks_new
                        set tracks.pline = tracks_new.pline ,   tracks.rights_granted =  tracks_new.rights_granted ,
                        tracks.title =  tracks_new.title,       tracks.explicit_lyrics =  tracks_new.explicit_lyrics ,
                        tracks.ioda_display_artist_name = tracks_new.ioda_display_artist_name 
                        where   tracks.track_id = tracks_new.track_id and 
                        theorchard.tracks.track_id =" . $track['track_id'];

        mysqli_query($orchard_dbs, $update_new_query);
        echo "\n";
    }
}
mysqli_close($orchard_dbs);
exit;
