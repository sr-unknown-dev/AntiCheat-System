# AntiCheat System
## Overview
A comprehensive anti-cheat solution for PocketMine-MP servers, designed to detect and prevent various types of cheating in real-time with minimal false positives.

## Prototype
## Features
### Multiple Cheat Detection
- AutoClick : Detects abnormal clicking patterns and speeds
- Reach : Identifies players attacking from impossible distances
- KillAura : Detects attacking multiple entities simultaneously
- Fly : Identifies unauthorized flight
- Aimbot : Detects suspicious aiming patterns
- Speed : Detects players moving faster than normally possible
### Smart Detection
- Ping compensation to reduce false positives
- Pattern analysis for more accurate detection
- Violation threshold system to prevent false bans
- Sprint-jump detection to prevent false positives while running
### Staff Tools
- Real-time alerts for staff members
- Discord webhook integration
- Exempt system for staff testing
### Punishment System
- Automatic temporary bans for confirmed cheaters
- Configurable ban durations
- Appeal system integration
- Automatic ban expiry checking
## Commands
- /ac alerts - Toggle anti-cheat alerts
- /ac exempt <player> [seconds] - Add/remove a player from the exempt list
- /ac unban <player> - Unban a player
## Configuration
The plugin is highly configurable through the config.yml file:

```yaml
# Discord webhook for alerts
discord:
  webhook: "https://discord.com/api/webhooks/your-webhook-url"
  link: "https://discord.gg/your-server"

# Alert settings
alerts:
  cooldown:
    autoclick: 1.0
    reach: 1.0
    speed: 1.0
    killaura: 1.0
    fly: 1.0
    aimbot: 1.0

# Debug settings
debug:
  enabled: false
  log_level: 1

# Check configurations
checks:
  autoclick:
    enabled: true
    max_autoclick_alerts: 20
    max_autoclick_ban: 25
    ping_threshold: 100
    violation_threshold: 3
  
  reach:
    enabled: true
    max_reach_alerts: 3.5
    max_reach_ban: 4.5
    violation_threshold: 3
  
  speed:
    enabled: true
    max_speed: 10
    max_speed_ban: 15
    ping_threshold: 100
    violation_threshold: 3
  
  killaura:
    enabled: true
    max_targets: 3
    max_rotation_speed: 40
    violation_threshold: 3
  
  fly:
    enabled: true
    max_air_time: 60
    violation_threshold: 5
    ignore_sprint_jump: true
  
  aimbot:
    enabled: true
    max_variance: 5.0
    violation_threshold: 3

# Punishment settings
punishments:
  default_ban_duration: "30d"
  ban_message: "&7Has sido BANEADO\nRazón: &6{reason}\n&7Expira en: {time}\n&7Si deseas apelar el ban: &6{discord}"
 ```
```

## Installation
1. Download the latest release from the releases page
2. Place the plugin in your server's plugins folder
3. Restart your server
4. Configure the plugin in the config.yml file
## Permissions
- anticheat.alerts - Allows players to receive anti-cheat alerts
- anticheat.exempt - Allows players to exempt others from checks
- anticheat.unban - Allows players to unban others
- anticheat.admin - Gives access to all AntiCheat commands
## Credits
- Developer : Sr_Unkn0wn
- Contributor : Trae AI - Assisted with code optimization and additional checks(Aimbot, KillAura, Speed)
- Special Thanks : PocketMine-MP Team for their amazing server software
## License
This project is licensed under the MIT License - see the LICENSE file for details.