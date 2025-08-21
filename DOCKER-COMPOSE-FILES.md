# Docker Compose Files Overview

This project includes multiple Docker Compose configurations for different use cases:

## 📋 Available Configurations

### `docker-compose.yml` (Standard/Production)
- **Purpose**: Production-ready setup with custom Docker images
- **Features**: 
  - Custom-built REDAXO images with pre-installed addons
  - Named Docker volumes for data persistence
  - Optimized for deployment and production use
- **Usage**: `docker compose up -d`

### `docker-compose.dev.yml` (Development)  
- **Purpose**: Development setup with live code mounting
- **Features**:
  - Mounts project directory for live editing
  - Development-optimized container configuration
  - Fast rebuild and iteration cycle
- **Usage**: `docker compose -f docker-compose.dev.yml up -d`

### `docker-compose.vscode.yml` (VSCode Extension Compatible)
- **Purpose**: Compatible with [redaxo-multi-instances-vscode](https://github.com/FriendsOfREDAXO/redaxo-multi-instances-vscode) extension
- **Features**:
  - **Custom-built REDAXO images with modern structure** (src/, public/, var/, bin/)
  - **All pre-installed addons** from addons.txt included
  - Service names `redaxo` and `mysql` (required by VSCode extension)
  - Bind mounts to `./data/redaxo/` with complete modern structure
  - SSL/HTTPS support with custom domains
  - Compatible with VSCode extension's port management
  - **Preserves all benefits of the modern structure**
- **Usage**: `docker compose -f docker-compose.vscode.yml up -d`
- **Setup**: Use `./setup-vscode.sh --setup` for easy configuration

## 🎯 Which One To Choose?

| Use Case | Recommended Configuration |
|----------|--------------------------|
| **Production Deployment** | `docker-compose.yml` |
| **Local Development** | `docker-compose.dev.yml` |
| **VSCode Extension Integration** | `docker-compose.vscode.yml` |
| **Multi-Instance Management** | `docker-compose.vscode.yml` + VSCode Extension |

## 🔧 Environment Configuration

Each configuration uses the same `.env` file format, but with specific optimizations:

- **Standard**: Copy from `.env.example`
- **VSCode Mode**: Copy from `.env.vscode.example`

## 📚 Documentation

- [Main Docker Guide](DOCKER.md)
- [VSCode Compatibility Guide](VSCODE-COMPATIBILITY.md)
- [Docker Examples](DOCKER-EXAMPLES.md)