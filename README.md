# CIS Modules System

**Central Information System (CIS) - Modular Web Application Framework**  
*Ecigdis Limited / The Vape Shed*

## Overview

The CIS Modules System is a modular web application framework designed for enterprise-level operations within The Vape Shed's retail and wholesale business. This system provides a scalable, maintainable architecture for managing various business operations including inventory transfers, consignments, purchase orders, and more.

## Architecture

### Core Components

- **Module Framework**: Standardized module structure with shared libraries and utilities
- **Consignment System**: Complete transfer management (pack/receive workflows)
- **Template Engine**: Reusable UI components and layouts
- **API Layer**: RESTful endpoints for module interactions
- **Security**: Enterprise-grade authentication and authorization

### Technology Stack

- **Backend**: PHP 8.1+ (PSR-12 compliant)
- **Frontend**: Bootstrap 4.2, Vanilla JavaScript (ES6+)
- **Database**: MySQL/MariaDB with optimized queries
- **Architecture**: MVC pattern with modular design
- **Hosting**: Cloudways managed servers

## Project Structure

```
modules/
├── _module/                    # Base module template
│   ├── index.php              # Module entry point
│   ├── _shared/               # Shared libraries
│   └── assets/                # CSS/JS resources
├── consignments/              # Transfer management module
│   ├── api/                   # RESTful endpoints
│   ├── components/            # UI components
│   ├── lib/                   # Module-specific libraries
│   └── pages/                 # Page controllers
├── CIS_TEMPLATE               # Module generator template
└── assets/                    # Global assets
```

## Key Features

### Consignment Management
- **Pack Workflow**: Efficient outbound transfer processing
- **Receive Workflow**: Streamlined inbound inventory management
- **Real-time Updates**: Live status tracking and notifications
- **Barcode Scanning**: Integration with handheld scanners
- **Exception Handling**: Comprehensive error management

### Module System
- **Standardized Structure**: Consistent architecture across modules
- **Shared Libraries**: Reusable components and utilities
- **Auto-loading**: PSR-4 compliant class loading
- **Configuration**: Environment-based settings

### Security Features
- **CSRF Protection**: Cross-site request forgery prevention
- **Input Validation**: Comprehensive data sanitization
- **Access Control**: Role-based permissions
- **Audit Logging**: Complete action tracking

## Development Standards

### Code Quality
- **PSR-12**: PHP coding standard compliance
- **Documentation**: Comprehensive inline documentation
- **Error Handling**: Structured error management
- **Logging**: Centralized logging system

### Security Practices
- **Input Sanitization**: All user input validated and sanitized
- **Parameterized Queries**: No SQL injection vulnerabilities
- **Environment Variables**: Secrets managed via .env files
- **HTTPS Only**: All communications encrypted

## Support

For technical support and questions:
- **Internal Wiki**: https://wiki.vapeshed.co.nz
- **Staff Portal**: https://staff.vapeshed.co.nz
- **IT Manager**: [Contact details in staff portal]

## License

Proprietary software - Ecigdis Limited. All rights reserved.

---

**Last Updated**: October 12, 2025  
**Version**: 2.0  
**Maintainer**: CIS Development Team
