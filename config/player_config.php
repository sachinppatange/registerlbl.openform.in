<?php
// Player Auction Registration System - Player Specific Config
// Latur Badminton League (LBL)

// वयोगट (Age Group) config: Start/End years, Label
const PLAYER_AGE_GROUPS = [
    [ 'min' => 30, 'max' => 40,  'label' => '30 ते 40' ],
    [ 'min' => 41, 'max' => 45,  'label' => '41 ते 45' ],
    [ 'min' => 46, 'max' => 50,  'label' => '46 ते 50' ],
    [ 'min' => 51, 'max' => 55,  'label' => '51 ते 55' ],
    [ 'min' => 56, 'max' => 120, 'label' => '55 च्या पुढे' ],
];

// वैध फोटो आणि आधार कार्ड extensions
const PLAYER_PHOTO_ALLOWED_EXT = ['jpg', 'jpeg', 'png'];
const PLAYER_AADHAAR_ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'pdf'];

// फाईल अपलोड size limit (bytes)
const PLAYER_PHOTO_MAX_SIZE = 2 * 1024 * 1024;     // 2 MB
const PLAYER_AADHAAR_MAX_SIZE = 2 * 1024 * 1024;   // 2 MB

// खेळण्याची वेळ (Time slots) - UI dropdown helper
const PLAYER_PLAY_TIMES = [
    "Morning (6am - 9am)",
    "Afternoon (12pm - 4pm)",
    "Evening (6pm - 10pm)",
    "Other"
];

// कोर्ट नाव dropdown helper
const PLAYER_COURT_NAMES = [
    "District Indoor Stadium",
    "Local Club Court",
    "School/College Court",
    "Other"
];

// गाव/शहर नाव default (Admin किंवा dynamic populate करू शकता)
const PLAYER_VILLAGE_NAMES = [
    "Latur", "Ausa", "Renapur", "Nilanga", "Udgir", "Other"
];

// नियम आणि अटी
const PLAYER_TERMS = <<<TEXT
मी भरलेली माहिती बरोबर आहे आणि सर्व नियम व अटी मान्य आहेत.
स्पर्धेसाठी योग्य माहिती व डॉक्युमेंट्स दिले आहेत.
स्पर्धेचे organizer/final authority निर्णय अंतिम असतील.
TEXT;

// Other custom config आता जोडता येईल.

?>