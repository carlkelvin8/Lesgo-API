# Clear Orders Data - Production Database

Scripts to safely clear all order data from production database during testing phase.

---

## 📋 What Gets Deleted

These scripts will delete ALL data from:

- ✓ **orders** table - All customer orders
- ✓ **order_items** table - All order items/products
- ✓ **chat_conversations** table - Order-related chats
- ✓ **chat_messages** table - Order-related messages
- ✓ **order_menu_item_options** table - Menu item options (if exists)

**Auto-increment IDs are reset to 1**

---

## 🚀 How to Use

### Option 1: PowerShell (Recommended for Windows)

```powershell
# Navigate to backend directory
cd BACKEND\lesgo-api

# Run the script
.\clear_orders_production.ps1
```

### Option 2: PHP Direct

```bash
# Navigate to backend directory
cd BACKEND/lesgo-api

# Run the script
php clear_orders_production.php
```

---

## 💾 Backup First (Optional but Recommended)

Before clearing, you can create a backup:

```bash
php backup_orders_before_clear.php
```

This creates a JSON backup in `storage/backups/orders_backup_YYYY-MM-DD_HH-MM-SS.json`

---

## ⚙️ What the Script Does

1. **Shows current data count** - Display how many orders will be deleted
2. **Asks for confirmation** - Press ENTER to continue or Ctrl+C to cancel
3. **Deletes in order** (transaction-safe):
   - Chat messages
   - Chat conversations
   - Order menu item options
   - Order items
   - Orders
4. **Resets auto-increment** - Next order ID will start from 1
5. **Shows summary** - Displays how many records were deleted

---

## 🔒 Safety Features

- ✅ **Transaction-based** - All-or-nothing deletion (if error occurs, rollback)
- ✅ **User confirmation** - Requires manual ENTER press before proceeding
- ✅ **Clear warnings** - Shows what will be deleted
- ✅ **Error handling** - Rollback on any error
- ✅ **Summary report** - Shows what was deleted

---

## 📊 Example Output

```
════════════════════════════════════════════════════════════════
  CLEAR ORDERS DATA - PRODUCTION DATABASE
════════════════════════════════════════════════════════════════

Environment: production
Database: lesgo_production

Current Data:
  - Orders: 45
  - Order Items: 123
  - Chat Messages: 89
  - Chat Conversations: 34

⚠️  WARNING: This will DELETE all order data!
⚠️  This action CANNOT be undone!

Press ENTER to continue or Ctrl+C to cancel...

Starting deletion process...

[1/5] Deleting chat messages...
      ✓ Deleted 89 chat messages

[2/5] Deleting chat conversations...
      ✓ Deleted 34 conversations

[3/5] Deleting order items...
      ✓ Deleted 123 order items

[4/5] Deleting order menu item options...
      ✓ Deleted 67 menu item options

[5/5] Deleting orders...
      ✓ Deleted 45 orders

════════════════════════════════════════════════════════════════
  ✓ SUCCESS: All order data deleted!
════════════════════════════════════════════════════════════════

Summary:
  - Orders deleted: 45
  - Order items deleted: 123
  - Chat messages deleted: 89
  - Chat conversations deleted: 34

Resetting auto-increment counters...
✓ Auto-increment counters reset

Database is now clean and ready for fresh testing!
```

---

## ⚠️ Important Notes

### Testing Phase Only
- Use ONLY during testing/development phase
- NOT for production with real customers
- Creates a clean slate for testing

### What Stays Intact
These tables are NOT affected:
- ✓ **users** - All customer accounts remain
- ✓ **partners** - All merchants/restaurants remain
- ✓ **menu_items** - All menu items remain
- ✓ **driver_profiles** - All riders remain
- ✓ **wallets** - Wallet balances remain
- ✓ **wallet_transactions** - Wallet history remains
- ✓ **addresses** - Saved addresses remain

### After Clearing
- Next order will have ID = 1
- All apps (customer, merchant, rider) will show empty order lists
- Users can place new orders normally
- No data corruption or integrity issues

---

## 🔄 Restore from Backup (if needed)

If you created a backup and need to restore:

1. Find backup file in `storage/backups/orders_backup_*.json`
2. Create restore script (manual process)
3. Import JSON data back to tables

---

## 🐛 Troubleshooting

### Error: "PHP not found"
- Install PHP: https://windows.php.net/download/
- Add PHP to system PATH

### Error: "Database connection failed"
- Check `.env` file has correct DB credentials
- Ensure MySQL/MariaDB is running
- Test connection: `php artisan db:show`

### Error: "Permission denied"
- Run PowerShell as Administrator
- Check file permissions

### Script hangs at confirmation
- Press ENTER key to continue
- Or press Ctrl+C to cancel

---

## 📝 Testing Workflow

**Recommended workflow:**

1. **Backup first** (optional but safe)
   ```bash
   php backup_orders_before_clear.php
   ```

2. **Clear orders**
   ```powershell
   .\clear_orders_production.ps1
   ```

3. **Test fresh**
   - Create new orders
   - Test order flow
   - Verify rider acceptance
   - Test chat functionality

4. **Repeat as needed**
   - Clear again when needed
   - No need to backup every time during active testing

---

## 📞 Support

If you encounter issues:
1. Check error message in console
2. Verify database connection
3. Check Laravel logs: `storage/logs/laravel.log`
4. Verify `.env` configuration

---

**Created:** June 19, 2026  
**Purpose:** Testing phase database cleanup  
**Safety:** Transaction-based with rollback on error
