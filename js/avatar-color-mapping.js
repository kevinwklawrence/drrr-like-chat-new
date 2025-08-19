// Avatar to Color Mapping Configuration
const AVATAR_COLOR_MAPPING = {
    // Time-limited avatars
    'time-limited/': 'spooky',
    
    // Default avatars by specific file
    'default/_u0.png': 'black',
    'default/bone.png': 'purple',
    'default/f1.png': 'orange',
    'default/f2.png': 'orange2',
    'default/f3.png': 'lavender',
    'default/f4.png': 'pink',
    'default/f5.png': 'rose',
    'default/f6.png': 'red',
    'default/f7.png': 'peach',
    'default/m1.png': 'blue',
    'default/m2.png': 'green',
    'default/m3.png': 'tan',
    'default/m4.png': 'yellow',
    'default/m5.png': 'black',
    'default/m6.png': 'gray',
    'default/m7.png': 'cobalt',
    'default/m8.png': 'black',
    'default/m9.png': 'urban',
    'default/icon_bb_tero.png': 'bbyellow',
    'default/icon_bepo.png': 'orange',
    'default/icon_bh.png': 'black',
    'default/icon_hakumen.png': 'gray',
    'default/icon_kakka.png': 'lavender2',
    'default/icon_meme.png': 'toyred',
    'default/icon_mudgreen.png': 'mudgreen',
    'default/icon_shingenb.png': 'deepbrown',
    'default/icon_teal2.png': 'teal2',
    'default/icon_xsoncho.png': 'palegreen',
    'default/nothing.png': 'negative',
    'default/pbrown.png': 'brown',
    'default/policeman2.png': 'policeman2',
    'default/train.png': 'navy',
    
    
    // Color folder mappings (folder name determines default color)
    'blue/': 'blue',
    'red/': 'red',
    'green/': 'green',
    'purple/': 'purple',
    'pink/': 'pink',
    'orange/': 'orange',
    'yellow/': 'yellow',
    'cyan/': 'cyan',
    'mint/': 'mint',
    'teal/': 'teal',
    'indigo/': 'indigo'
};

// Global fallback color when no mapping is found
const DEFAULT_FALLBACK_COLOR = 'black';

function getAvatarDefaultColor(avatarPath) {
    if (!avatarPath) return DEFAULT_FALLBACK_COLOR;
    
    // Check for exact file matches first
    if (AVATAR_COLOR_MAPPING[avatarPath]) {
        return AVATAR_COLOR_MAPPING[avatarPath];
    }
    
    // Check for folder-based matches
    for (const [pattern, color] of Object.entries(AVATAR_COLOR_MAPPING)) {
        if (pattern.endsWith('/') && avatarPath.startsWith(pattern)) {
            return color;
        }
    }
    
    // Extract folder from path and check if it matches a color name
    const folder = avatarPath.split('/')[0];
    const validColors = ['blue', 'purple', 'pink', 'cyan', 'mint', 'orange', 'green', 'yellow', 'red', 'teal', 'indigo','spooky'];
    
    if (validColors.includes(folder.toLowerCase())) {
        return folder.toLowerCase();
    }
    
    return DEFAULT_FALLBACK_COLOR;
}