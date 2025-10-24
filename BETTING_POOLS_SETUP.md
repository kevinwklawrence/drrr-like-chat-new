# Betting Pools Setup Guide

This guide will help you set up the betting pools feature for your DRRR-like chat application.

## Prerequisites

- Admin access to the application
- Database access

## Installation Steps

### 1. Create Database Tables

Run the setup script to create the necessary database tables:

1. Log in as an admin user
2. Navigate to `setup_betting_pools.php` in your browser
3. The script will create:
   - `betting_pools` table: Stores betting pool information
   - `betting_pool_bets` table: Stores individual user bets

### 2. Verify Installation

The betting pools feature has been integrated into the room interface with the following components:

**Backend:**
- `api/betting_pool.php` - API endpoint for all betting pool operations
- `setup_betting_pools.php` - Database setup script

**Frontend:**
- `js/betting_pool.js` - JavaScript functionality
- `css/betting_pool.css` - Styling

**Modified Files:**
- `room.php` - Added betting pool button and included scripts/styles
- `api/get_room_users.php` - Added bet amount to user data
- `js/room.js` - Added bet amount badge display and initialization

## Features

### For Hosts, Moderators, and Admins

1. **Create Betting Pool**
   - Click the coins icon (💰) in the room header
   - Enter a title and optional description
   - Only one active pool per room at a time

2. **Select Winner**
   - Click "Select Winner" in the pool widget
   - Choose a participant from the list
   - Winner receives the entire pool

3. **Close Pool**
   - Click "Close & Refund" to cancel the pool
   - All bets are refunded to participants

### For All Users

1. **Place Bet**
   - Click "Place Bet" in the pool widget
   - Enter the amount of Dura to bet
   - Bet is deducted from your balance immediately
   - You can only bet once per pool

2. **View Pool Status**
   - Current total pool amount
   - Number of participants
   - Your bet amount (if you've placed a bet)

## System Behavior

- **Bet Display**: User's bet amount appears as a badge in the user list
- **System Messages**: Pool creation, bets, and winner selection are announced in chat
- **Automatic Updates**: Pool information refreshes every 5 seconds
- **Winner Payout**: Winner receives the total pool added to their Dura balance

## Database Schema

### betting_pools
- `id`: Primary key
- `room_id`: Foreign key to rooms
- `title`: Pool title
- `description`: Optional description
- `created_by`: Creator's user ID (NULL for guests)
- `created_by_user_id_string`: Creator's unique ID
- `created_by_username`: Creator's username
- `total_pool`: Total Dura in pool
- `status`: 'active', 'closed', or 'completed'
- `winner_user_id_string`: Winner's unique ID
- `winner_username`: Winner's username
- `created_at`: Creation timestamp
- `closed_at`: Closure timestamp

### betting_pool_bets
- `id`: Primary key
- `pool_id`: Foreign key to betting_pools
- `user_id`: Bettor's user ID (NULL for guests)
- `user_id_string`: Bettor's unique ID
- `username`: Bettor's username
- `bet_amount`: Amount of Dura bet
- `placed_at`: Bet timestamp
- Unique constraint: One bet per user per pool

## Permissions

- **Create Pool**: Hosts, Moderators, Admins
- **Place Bet**: Registered users only (guests cannot bet)
- **Select Winner**: Hosts, Moderators, Admins
- **Close Pool**: Hosts, Moderators, Admins

## Notes

- Guests can see pools but cannot place bets (they don't have Dura)
- Only registered users' bets are refunded if pool is closed
- Winner must have placed a bet to be selected
- System prevents duplicate bets from the same user

## Troubleshooting

If betting pools aren't working:

1. Check that database tables were created successfully
2. Verify all files are in the correct locations
3. Clear browser cache to reload JavaScript and CSS
4. Check browser console for errors
5. Verify user has sufficient permissions (host/moderator/admin to create pools)
6. Ensure user is registered to place bets

## API Endpoints

- `POST api/betting_pool.php?action=create_pool` - Create new pool
- `POST api/betting_pool.php?action=place_bet` - Place bet
- `POST api/betting_pool.php?action=select_winner` - Select winner
- `POST api/betting_pool.php?action=close_pool` - Close and refund
- `POST api/betting_pool.php?action=get_pool_info` - Get pool information
