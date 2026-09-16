#!/usr/bin/env bash
# PanelAlpha Container Management Script
# Usage: bash container-manager.sh [engine|users] [start|stop|restart|status|logs|up|down] [username]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default paths
PANELALPHA_DIR="/opt/panelalpha"
ENGINE_COMPOSE_FILE="${PANELALPHA_DIR}/shared-hosting/docker-compose.yml"
USERS_DIR="${PANELALPHA_DIR}/users"

# Functions
log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_debug() {
    echo -e "${BLUE}[DEBUG]${NC} $1"
}

show_help() {
    echo "PanelAlpha Container Management Script"
    echo ""
    echo "Usage:"
    echo "  $0 engine [start|stop|restart|status|logs]"
    echo "  $0 users [up|down|restart|status|logs] [username]"
    echo ""
    echo "Commands:"
    echo "  engine:"
    echo "    start   - Start PanelAlpha engine containers"
    echo "    stop    - Stop PanelAlpha engine containers"
    echo "    restart - Restart PanelAlpha engine containers"
    echo "    status  - Show status of PanelAlpha engine containers"
    echo "    logs    - Show logs of PanelAlpha engine containers"
    echo ""
    echo "  users:"
    echo "    up      - Start containers for specific user or all users"
    echo "    down    - Stop containers for specific user or all users"
    echo "    restart - Restart containers for specific user or all users"
    echo "    status  - Show status of user containers"
    echo "    logs    - Show logs of user containers"
    echo ""
    echo "Examples:"
    echo "  $0 engine start"
    echo "  $0 engine logs"
    echo "  $0 users up 123"
    echo "  $0 users down all"
    echo "  $0 users status"
}

check_dependencies() {
    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed or not in PATH"
        exit 1
    fi

    if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
        log_error "Docker Compose is not installed or not in PATH"
        exit 1
    fi
}

# Engine management functions
engine_start() {
    log_info "Starting PanelAlpha engine containers..."
    if [ -f "$ENGINE_COMPOSE_FILE" ]; then
        docker compose -f "$ENGINE_COMPOSE_FILE" up -d
        log_info "PanelAlpha engine containers started successfully"
    else
        log_error "Engine compose file not found: $ENGINE_COMPOSE_FILE"
        exit 1
    fi
}

engine_stop() {
    log_info "Stopping PanelAlpha engine containers..."
    if [ -f "$ENGINE_COMPOSE_FILE" ]; then
        docker compose -f "$ENGINE_COMPOSE_FILE" down
        log_info "PanelAlpha engine containers stopped successfully"
    else
        log_error "Engine compose file not found: $ENGINE_COMPOSE_FILE"
        exit 1
    fi
}

engine_restart() {
    log_info "Restarting PanelAlpha engine containers..."
    engine_stop
    sleep 2
    engine_start
}

engine_status() {
    log_info "PanelAlpha engine containers status:"
    if [ -f "$ENGINE_COMPOSE_FILE" ]; then
        docker compose -f "$ENGINE_COMPOSE_FILE" ps
    else
        log_error "Engine compose file not found: $ENGINE_COMPOSE_FILE"
        exit 1
    fi
}

engine_logs() {
    log_info "PanelAlpha engine containers logs:"
    if [ -f "$ENGINE_COMPOSE_FILE" ]; then
        docker compose -f "$ENGINE_COMPOSE_FILE" logs -f --tail=100
    else
        log_error "Engine compose file not found: $ENGINE_COMPOSE_FILE"
        exit 1
    fi
}

# User management functions
get_user_compose_file() {
    local username=$1
    echo "${USERS_DIR}/${username}/docker-compose.yml"
}

list_users() {
    if [ -d "$USERS_DIR" ]; then
        ls -1 "$USERS_DIR" 2>/dev/null | grep -E '^[0-9]+$' || true
    fi
}

users_up() {
    local username=$1

    if [ "$username" = "all" ]; then
        log_info "Starting containers for all users..."
        for user_dir in $(list_users); do
            local compose_file=$(get_user_compose_file "$user_dir")
            if [ -f "$compose_file" ]; then
                log_debug "Starting containers for user $user_dir..."
                docker compose -f "$compose_file" up -d
            else
                log_warn "Compose file not found for user $user_dir: $compose_file"
            fi
        done
        log_info "All user containers started"
    else
        local compose_file=$(get_user_compose_file "$username")
        if [ -f "$compose_file" ]; then
            log_info "Starting containers for user $username..."
            docker compose -f "$compose_file" up -d
            log_info "User $username containers started successfully"
        else
            log_error "Compose file not found for user $username: $compose_file"
            exit 1
        fi
    fi
}

users_down() {
    local username=$1

    if [ "$username" = "all" ]; then
        log_info "Stopping containers for all users..."
        for user_dir in $(list_users); do
            local compose_file=$(get_user_compose_file "$user_dir")
            if [ -f "$compose_file" ]; then
                log_debug "Stopping containers for user $user_dir..."
                docker compose -f "$compose_file" down
            else
                log_warn "Compose file not found for user $user_dir: $compose_file"
            fi
        done
        log_info "All user containers stopped"
    else
        local compose_file=$(get_user_compose_file "$username")
        if [ -f "$compose_file" ]; then
            log_info "Stopping containers for user $username..."
            docker compose -f "$compose_file" down
            log_info "User $username containers stopped successfully"
        else
            log_error "Compose file not found for user $username: $compose_file"
            exit 1
        fi
    fi
}

users_restart() {
    local username=$1
    log_info "Restarting containers for user $username..."
    users_down "$username"
    sleep 2
    users_up "$username"
}

users_status() {
    log_info "User containers status:"
    echo "User ID | Status | Containers"
    echo "--------|--------|-----------"

    for user_dir in $(list_users); do
        local compose_file=$(get_user_compose_file "$user_dir")
        if [ -f "$compose_file" ]; then
            local status=$(docker compose -f "$compose_file" ps --format "table {{.Names}}|{{.Status}}" | tail -n +2 | tr '\n' ' ' | sed 's/ $//')
            if [ -n "$status" ]; then
                echo "$user_dir | Running | $status"
            else
                echo "$user_dir | Stopped | No containers"
            fi
        else
            echo "$user_dir | No compose file | N/A"
        fi
    done
}

users_logs() {
    local username=$1

    if [ "$username" = "all" ]; then
        log_info "Showing logs for all users (this may be verbose)..."
        for user_dir in $(list_users); do
            local compose_file=$(get_user_compose_file "$user_dir")
            if [ -f "$compose_file" ]; then
                log_debug "Logs for user $user_dir:"
                docker compose -f "$compose_file" logs --tail=50
                echo "---"
            fi
        done
    else
        local compose_file=$(get_user_compose_file "$username")
        if [ -f "$compose_file" ]; then
            log_info "Logs for user $username:"
            docker compose -f "$compose_file" logs -f --tail=100
        else
            log_error "Compose file not found for user $username: $compose_file"
            exit 1
        fi
    fi
}

# Main logic
main() {
    local component=$1
    local action=$2
    local username=$3

    check_dependencies

    case $component in
        engine)
            case $action in
                start)
                    engine_start
                    ;;
                stop)
                    engine_stop
                    ;;
                restart)
                    engine_restart
                    ;;
                status)
                    engine_status
                    ;;
                logs)
                    engine_logs
                    ;;
                *)
                    log_error "Invalid engine action: $action"
                    echo ""
                    show_help
                    exit 1
                    ;;
            esac
            ;;
        users)
            case $action in
                up)
                    if [ -z "$username" ]; then
                        log_error "Username is required for 'up' action. Use 'all' for all users."
                        exit 1
                    fi
                    users_up "$username"
                    ;;
                down)
                    if [ -z "$username" ]; then
                        log_error "Username is required for 'down' action. Use 'all' for all users."
                        exit 1
                    fi
                    users_down "$username"
                    ;;
                restart)
                    if [ -z "$username" ]; then
                        log_error "Username is required for 'restart' action. Use 'all' for all users."
                        exit 1
                    fi
                    users_restart "$username"
                    ;;
                status)
                    users_status
                    ;;
                logs)
                    users_logs "$username"
                    ;;
                *)
                    log_error "Invalid users action: $action"
                    echo ""
                    show_help
                    exit 1
                    ;;
            esac
            ;;
        *)
            log_error "Invalid component: $component"
            echo ""
            show_help
            exit 1
            ;;
    esac
}

# Run main function with all arguments
if [ $# -eq 0 ]; then
    show_help
    exit 0
fi

main "$@"