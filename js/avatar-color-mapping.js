// Avatar to Color Mapping Configuration
const AVATAR_COLOR_MAPPING = {
    // Time-limited avatars
    'time-limited/': 'spooky',
    
    // Default avatars by specific file
    'default/_u0.png': 'black',
    'default/zbone.png': 'purple',
    'default/sushi.png': 'purple',
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
    'default/nothing.png': 'negative',
    'default/pbrown.png': 'brown',
    'default/policeman2.png': 'policeman2',
    'default/train.png': 'navy',
    'mushoku/icon_bb_tero.png': 'bbyellow',
    'mushoku/icon_bepo.png': 'orange',
    'mushoku/icon_bh.png': 'black',
    'mushoku/icon_hakumen.png': 'gray',
    'mushoku/icon_kakka.png': 'lavender2',
    'mushoku/icon_meme.png': 'toyred',
    'mushoku/icon_mudgreen.png': 'mudgreen',
    'mushoku/icon_shingenb.png': 'deepbrown',
    'mushoku/icon_teal2.png': 'teal2',
    'mushoku/icon_xsoncho.png': 'palegreen',
    'mushoku/icon_chii.png': 'chiipink',
    'mushoku/icon_ciel.png': 'forest',
    'mushoku/icon_hazama.png': 'forest',
    'mushoku/icon_keroro.png': 'palegreen',
    'mushoku/icon_dango.png': 'tan',
    'mushoku/icon_mihashi.png': 'tan',
    'mushoku/icon_law.png': 'tan',
    'mushoku/icon_hana.png': 'rust',
    'mushoku/icon_hongkong.png': 'toyred',
    'mushoku/icon_dark_blue.png': 'cobalt',
    'mushoku/icon_tama.png': 'cobalt',
    'mushoku/icon_shizou.png': 'navy',
    'mushoku/icon_haruhi.png': 'babyblue',
    'mushoku/icon_nozomu.png': 'sepia',
    'mushoku/icon_mika2.png': 'lavender',
    'mushoku/icon_hoshi.png': 'yellow',
    'mushoku/icon_konoe.png': 'yellow',
    'mushoku/icon_selty.png': 'bbyellow',
    'mushoku/icon_ren.png': 'yellow',
    'drrrjp/blue.png': 'babyblue',
    'drrrjp/blue2.png': 'babyblue',
    'drrrjp/blue3.png': 'babyblue',
    'drrrjp/blue4.png': 'babyblue',
    'drrrjp/blue5.png': 'cobalt',
    'drrrjp/blue6.png': 'navy',
    'drrrjp/blue7.png': 'cobalt',
    'drrrjp/black.png': 'black',
    'drrrjp/black2.png': 'black',
    'drrrjp/black3.png': 'black',
    'drrrjp/gray.png': 'gray',
    'drrrjp/gray2.png': 'gray',
    'drrrjp/gray3.png': 'gray',
    'drrrjp/gray4.png': 'gray',
    'drrrjp/gray5.png': 'gray',
    'drrrjp/green.png': 'green',
    'drrrjp/green2.png': 'green',
    'drrrjp/green3.png': 'green',
    'drrrjp/green4.png': 'green',
    'drrrjp/orange.png': 'orange',
    'drrrjp/orange2.png': 'orange',
    'drrrjp/orange3.png': 'orange',
    'drrrjp/orange4.png': 'orange',
    'drrrjp/orange5.png': 'orange',
    'drrrjp/orange6.png': 'orange2',
    'drrrjp/pink.png': 'pink',
    'drrrjp/pink2.png': 'pink',
    'drrrjp/pink3.png': 'pink',
    'drrrjp/pink4.png': 'pink',
    'drrrjp/pink5.png': 'pink',
    'drrrjp/pink6.png': 'rose',
    'drrrjp/purple.png': 'lavender',
    'drrrjp/purple2.png': 'lavender',
    'drrrjp/purple3.png': 'lavender',
    'drrrjp/purple4.png': 'lavender',
    'drrrjp/purple5.png': 'lavender',
    'drrrjp/red.png': 'red',
    'drrrjp/red2.png': 'red',
    'drrrjp/red3.png': 'red',
    'drrrjp/red4.png': 'red',
    'drrrjp/red5.png': 'toyred',
    'drrrjp/yellow.png': 'bbyellow',
    'drrrjp/yellow2.png': 'bbyellow',
    'drrrjp/yellow3.png': 'bbyellow',
    'drrrjp/yellow4.png': 'bbyellow',
    'drrrjp/yellow5.png': 'bbyellow',
    'community/rat_chef.png': 'gray',
    
    
    
    
    // Color folder mappings (folder name determines default color)
    'blue/': 'blue',
    'red/': 'red',
    'green/': 'green',
    'purple/': 'lavender',
    'pink/': 'pink',
    'orange/': 'orange',
    'yellow/': 'yellow',
    'brown/': 'brown',
    'gray/': 'gray',
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