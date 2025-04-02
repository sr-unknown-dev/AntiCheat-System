AntiCheat System
A comprehensive anti-cheat solution for PocketMine-MP servers, designed to detect and prevent various types of cheating.

Features
Multiple Cheat Detection:

AutoClick: Detects abnormal clicking patterns and speeds
Reach: Identifies players attacking from impossible distances
KillAura: Detects attacking multiple entities simultaneously
Fly: Identifies unauthorized flight
Aimbot: Detects suspicious aiming patterns
Smart Detection:

Ping compensation to reduce false positives
Pattern analysis for more accurate detection
Violation threshold system to prevent false bans
Staff Tools:

Real-time alerts for staff members
Discord webhook integration
Exempt system for staff testing
Punishment System:

Automatic temporary bans for confirmed cheaters
Configurable ban durations
Appeal system integration
Commands
/ac alerts - Toggle anti-cheat alerts
/ac exempt <player> - Add/remove a player from the exempt list
/ac unban <player> - Unban a player
Configuration
The plugin is highly configurable through the config.yml file:

yaml

# Discord webhook for alertsalerts:  webhook: "https://discord.  com/api/webhooks/  your-webhook-url"  cooldown:    autoclick: 1.0    reach: 1.0    killaura: 1.0    fly: 1.0    aimbot: 1.0# Discord server link for ban appealsdiscord-link: "https://discord.gg/your-server"# Check configurationschecks:  autoclick:    max_autoclick_alerts: 20    max_autoclick_ban: 25    ping_threshold: 100    violation_threshold: 3  reach:    max_reach_alerts: 3.5    max_reach_ban: 4.5    violation_threshold: 3  killaura:    max_targets: 3    max_rotation_speed: 40    violation_threshold: 3  fly:    max_air_time: 40    violation_threshold: 3  aimbot:    max_variance: 5.0    violation_threshold: 3
Installation
Download the latest release from the releases page
Place the plugin in your server's plugins folder
Restart your server
Configure the plugin in the config.yml file
Permissions
anticheat.alerts - Allows players to receive anti-cheat alerts
anticheat.exempt - Allows players to exempt others from checks
anticheat.unban - Allows players to unban others
Credits
Developer: Your Name
Contributor: Trae AI - Assisted with code optimization and additional checks
Special Thanks: PocketMine-MP Team for their amazing server software
License
This project is licensed under the MIT License - see the LICENSE file for details.