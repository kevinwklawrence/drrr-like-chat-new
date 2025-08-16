// Avatar to Color Mapping Configuration
const AVATAR_COLOR_MAPPING = {
    // Time-limited avatars
    'time-limited/': 'purple',
    
    // Default avatars by specific file
    'default/_u0.png': 'black',
    'default/bone.png': 'purple',
    'default/f1.png': 'orange',
    
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
    const validColors = ['blue', 'purple', 'pink', 'cyan', 'mint', 'orange', 'green', 'yellow', 'red', 'teal', 'indigo'];
    
    if (validColors.includes(folder.toLowerCase())) {
        return folder.toLowerCase();
    }
    
    return DEFAULT_FALLBACK_COLOR;
}