# Voyti API — REST API for Voyti

The REST API base package for [Voyti](https://github.com/YiiRocks/voyti), the Yii3 user-management extension. It carries the whole read-only and CRUD user API: JSON endpoints, bearer-token authentication, an OpenAPI 3.1 specification, and console commands to issue and revoke API tokens.

[![Packagist Version](https://img.shields.io/packagist/v/yiirocks/voyti-api.svg)](https://packagist.org/packages/yiirocks/voyti-api)
[![PHP from Packagist](https://img.shields.io/packagist/php-v/yiirocks/voyti-api.svg)](https://php.net/)
[![Packagist](https://img.shields.io/packagist/dt/yiirocks/voyti-api.svg)](https://packagist.org/packages/yiirocks/voyti-api)
[![GitHub License](https://img.shields.io/github/license/yiirocks/voyti-api.svg)](https://github.com/yiirocks/voyti-api/blob/main/LICENSE.md)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/yiirocks/voyti-api/build.yml?branch=main)](https://github.com/yiirocks/voyti-api/actions)

Stats for Nerds

[![Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-api%2Fbadges%2Fcoverage.json)](https://github.com/yiirocks/voyti-api/tree/badges)
[![MSI](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-api%2Fbadges%2Fmsi.json)](https://github.com/yiirocks/voyti-api/tree/badges)
[![Tests](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-api%2Fbadges%2Ftests.json)](https://github.com/yiirocks/voyti-api/tree/badges)
[![Assertions](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fyiirocks%2Fvoyti-api%2Fbadges%2Fassertions.json)](https://github.com/yiirocks/voyti-api/tree/badges)

## Overview

The REST API is pluggable in Voyti: it mounts as its own route group (`voyti-routes-api`) and protects the endpoints with bearer tokens issued and revoked through the `voyti:api-token:generate` and `voyti:api-token:revoke` console commands. Tokens are persisted only as SHA-256 hashes and are only accepted for a configurable lifespan. A `voyti/api-openapi` endpoint serves the OpenAPI 3.1 specification describing the whole surface.

## Installation

```bash
composer require yiirocks/voyti-api
```

## Documentation

The complete reference guide is available at [Yii.Rocks](https://www.yii.rocks/voyti/api/).
