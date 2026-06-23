<?php
$bucket_name = getenv('WEBCMS_S3_BUCKET');
$region_name = getenv('WEBCMS_S3_REGION');
print "BUCKET: ".$bucket_name;
print "REGION: ".$region_name;
