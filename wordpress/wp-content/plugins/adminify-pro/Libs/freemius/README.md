# Freemius SDK Modifications

This document outlines the modifications made to the Freemius WordPress SDK to bypass licensing requirements and enable all premium features automatically.

**Modified by:** GPL Times (https://www.gpltimes.com)

## Summary of Changes

The following key modifications have been implemented to:
1. Disable license activation requirements
2. Prevent external API calls
3. Automatically enable all premium features
4. Ensure this modified SDK is always loaded first when multiple plugins use Freemius

## Modified Files

### 1. `start.php`

#### Modifications:

- **SDK Version**: Changed from `2.12.0` to `999.99.99` to ensure this modified SDK is always loaded first when multiple plugins with Freemius are active
- WordPress always loads the newest SDK version from all active plugins, so this ensures our modified version takes precedence

### 2. `includes/class-freemius.php`

#### Method Modifications:

- **`is_paying()`** - Always returns `true` to bypass license checks
- **`is_free_plan()`** - Always returns `false` to indicate premium plan
- **`has_features_enabled_license()`** - Always returns `true` to enable premium features
- **`can_use_premium_code()`** - Always returns `true` to allow premium code usage
- **`is_trial()`** - Always returns `false` to simulate paid license (not trial)
- **`is_trial_utilized()`** - Always returns `false` to allow trials
- **`is_registered()`** - Always returns `true` to simulate registration
- **`has_active_valid_license()`** - Always returns `true` to simulate active license
- **`is_active_valid_license()`** - Always returns `true` to simulate valid license
- **`get_user()`** - Returns mock user object if none exists to prevent account page errors
- **`get_site()`** - Returns mock site object if none exists to prevent account page errors
- **`_get_license()`** - Returns mock license object if none exists to prevent account page errors
- **`get_plan()`** - Returns mock "Professional" plan if none exists to prevent account page errors
- **`_add_trial_notice()`** - Always returns false to disable trial notices completely
- **`_ensure_mock_objects()`** - Private method that creates all necessary mock objects early in initialization
- **`_fetch_payments()`** - Returns empty array instead of making API calls to avoid count() errors
- **`_fetch_billing()`** - Returns false instead of making API calls to avoid billing errors
- **`has_api_connectivity()`** - Always returns true to show "Connected" status in debug page instead of "Unknown"
- **`_get_plan_by_id()`** - Added null array checks and returns mock "Professional Plan" to prevent foreach errors
- **`get_plan_by_name()`** - Added null array checks to prevent foreach errors
- **`_sync_license()`** - Bypassed completely to prevent sync-related API errors
- **`_sync_plugin_license()`** - Bypassed completely to prevent undefined property errors on $result->error

#### Other Changes:

- Modified license activation requirement to always set `require_license_activation = false`
- Added early initialization of mock objects in constructor to prevent errors throughout the system

### 3. `includes/entities/class-fs-plugin-license.php`

#### Method Modifications:

- **`is_features_enabled()`** - Always returns `true` to enable premium features
- **`is_active()`** - Always returns `true` to simulate active license
- **`is_expired()`** - Always returns `false` to simulate non-expired license

### 4. `includes/sdk/FreemiusWordPress.php`

#### Method Modifications:

- **`MakeRequest()`** - Bypasses all external API calls and returns empty success response
- **`Ping()`** - Always returns successful ping to simulate connectivity

### 5. `includes/class-fs-api.php`

#### Method Modifications:

- **`_call()`** - Bypasses all API calls and returns empty success response

### 6. `config.php`

#### Configuration Changes:

- **`WP_FS__DEV_MODE`** - Set to `false` to disable developer mode
- **`WP_FS__MOCK_PLAN_NAME`** - Constant for mock plan name (default: `'professional'`)
- **`WP_FS__MOCK_PLAN_TITLE`** - Constant for mock plan title (default: `'Professional Plan'`)

## Effect of Changes

After these modifications:

1. **No License Activation Required**: Plugins using this SDK will not prompt for license activation
2. **No External API Calls**: All communication with Freemius servers is bypassed
3. **Full Premium Access**: All premium features are automatically enabled
4. **Simulated Valid License**: The system behaves as if it has a valid, active premium license with key `sk_b5e0b5f8dd8689e6aca49dd6e6e1a930`
5. **No Trial Messages**: Shows as paid license instead of trial
6. **Account Page Fixed**: Mock objects prevent account page errors
7. **SDK Priority**: This modified SDK is always loaded first when multiple plugins with Freemius are active
8. **Trial Notices Disabled**: No trial promotion popups or messages will appear
9. **Plugin Activation Fixed**: Resolved errors during plugin activation process
10. **Professional Plan**: Shows as "Professional Plan" instead of "FREE"
11. **Debug Page Fixed**: Shows "Connected" status with green background instead of "Unknown" in red
12. **Mock User**: Uses email `noreply@gmail.com` for account information

## Important Notes

- These modifications completely bypass the Freemius licensing and payment system
- All plugins using this modified SDK will have unlimited access to premium features
- No validation or verification of actual licenses occurs
- API connectivity tests always return successful responses
- Registration and opt-in processes are bypassed

## Easy Plan Configuration

The plan name and title can now be easily changed from one location using constants in `config.php`:

- **`WP_FS__MOCK_PLAN_NAME`** - Controls the internal plan name (e.g., 'professional', 'premium', 'enterprise')
- **`WP_FS__MOCK_PLAN_TITLE`** - Controls the displayed plan title (e.g., 'Professional Plan', 'Premium Plan', 'Enterprise Plan')

To change the plan displayed throughout the system, simply modify these constants in the `config.php` file and all mock plan objects will automatically use the new values.

## Mock Data Configuration

The following mock data is configured in the `_ensure_mock_objects()` method:

### Mock User Object:
- **ID**: `1`
- **Email**: `noreply@gmail.com`
- **Name**: `Premium User`
- **Public Key**: `pk_4a7c9e2f8b3d1a6e5c8f2b9d4a7c0e3f`
- **Secret Key**: `sk_8f3d1a6e5c2b9d4a7c0e3f6b8a1d4e7c`

### Mock Site Object:
- **ID**: `1`
- **License ID**: `1`
- **Plan ID**: `1`
- **Public Key**: `pk_f3b8c2a7e9d1f4a6c3e8b2d5a9f1c4e7`
- **Secret Key**: `sk_2d5a9f1c4e7b3a6c8f2e1d4a7c0e3f6b`

### Mock License Object:
- **ID**: `1`
- **Plan ID**: `1`
- **Secret Key**: `sk_b5e0b5f8dd8689e6aca49dd6e6e1a930`
- **Quota**: `null` (unlimited)
- **Expiration**: `null` (lifetime)

### Mock Plan Object:
- **ID**: `1`
- **Name**: `WP_FS__MOCK_PLAN_NAME` (default: `'professional'`)
- **Title**: `WP_FS__MOCK_PLAN_TITLE` (default: `'Professional Plan'`)
- **Type**: `paid`

## Technical Implementation Details

### API Bypass Strategy:
1. **FreemiusWordPress.php**: `MakeRequest()` returns `(object)['success' => true]`
2. **class-fs-api.php**: `_call()` returns `(object)['success' => true]`
3. **All sync methods**: Return early to prevent API calls

### License Check Overrides:
- All `is_*()` methods related to licensing return appropriate boolean values
- Mock object creation prevents null reference errors
- Plans array initialization prevents foreach errors

### Error Prevention:
- Null checks added to prevent "Undefined property" errors
- Array initialization to prevent foreach errors
- Early returns in sync methods to bypass API dependencies

### SDK Loading Priority:
- SDK version set to `999.99.99` in `start.php` to ensure it's always loaded first
- WordPress automatically selects the highest version SDK from all active plugins

## Usage

Simply replace the original Freemius SDK files with these modified versions in any WordPress plugin that uses Freemius, and all premium features will be automatically available without license activation.

## Reapplication to New SDK Versions

To apply these modifications to a new SDK version:

1. **Backup the original SDK files**
2. **Apply all method modifications listed above** to their respective files
3. **Set the SDK version to `999.99.99` in `start.php`** to ensure it's loaded first
4. **Test account page functionality** to ensure no errors
5. **Verify premium features are enabled** without license activation
6. **Check debug page shows "Connected" status**

All modifications are designed to be minimally invasive and focused on specific method returns rather than structural changes, making them easier to reapply to future versions.

## Quick Plan Customization

To customize the plan name and title shown throughout the system:

1. **Open `config.php`**
2. **Modify the constants**:
   ```php
   define( 'WP_FS__MOCK_PLAN_NAME', 'your_plan_name' );
   define( 'WP_FS__MOCK_PLAN_TITLE', 'Your Plan Title' );
   ```
3. **Save the file** - All mock plan objects will automatically use the new values

Examples:
- For Enterprise: `'enterprise'` and `'Enterprise Plan'`
- For Premium: `'premium'` and `'Premium Plan'`
- For Pro: `'pro'` and `'Pro Plan'`