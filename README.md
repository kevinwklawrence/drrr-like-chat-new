
![Example Image](/images/bg/logo.png)

# ![Example Image](/images/staff/admin.png) DRRR-LIKE-CHAT-NEW
A new drrr-like-chat project made from scratch, using HTML, CSS, JS, PHP, Ajax, and SQL. 

©Lenn, 2025.

# ![Example Image](/images/orange/icon_fullmetal.png) FEATURES
- Guest Login
- User Login
- Registration
- Room passwords
- Room descriptions
- Hosts
- Host passing/Host room nuking
- Auto-deleting rooms
- Permanent rooms
- Room settings/Kick/Ban
- Knocking
- Change avatar in lounge
- Time-limited avatars
- Event-limited avatars

# ![Example Image](/images/red/icon_red_cat.png) PLANNED FEATURES
- Private messaging
- Custom avatars for certain users
- Avatar memory
- Message effects (Wiggly, disappearing, etc)
- Message lifespans (temporary message mode)
- Room accessories (identifiers)
- Website events (Holiday themes)
- Message "attachments"
   - Images
   - Drawings
   - Voice memos
   - Games
- Room music/videos (embedded YouTube videos built into room)
- Titles, accessories, and pets
- "Dollars" karma system, can trade karma for titles, accessories, pets, avatars, etc


# ![Example Image](/images/black/policeman2.png) KNOWN ISSUES
- Users and hosts not properly showing up on room listings
- Room settings don't have option to turn off/on password and knocking
- Deleted rooms don't kick all users properly
- Banned users not immediately being kicked from Room
- Lounge not properly showing all avatars
- Lounge does not properly refresh when user is given room key

# WHY SHOULD I USE THIS OVER THE ORIGINAL?
Because the original sucks. The source code is extremely outdated, and it was never maintained to keep up with more current versions of the languages it uses, leading to a lot of errors involving deprecated functions. This version also has a ton of new features 
other drrr-like-chat projects don't have. 

# HOW DO I SET UP THE SQL?
There is a file labed "sql_setup.txt" in the root directory, with SQL code which you just insert directly into your database. It will make everything for you.

# WHY DO THE PASSWORDS LOOK WEIRD?
Because they're encrypted. There is a key inside of the files which you can use to decrypt the encrypted strings. You should also change this key, since this is open source.

# HOW DO I ADD NEW AVATARS?
Simply add the new avatars into the respective folder, with the "default" folder containing all of the guest-only icons. Then, you can follow the style.css file to learn how to customize these avatars. 
