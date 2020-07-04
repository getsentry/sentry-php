# Upgrade 2.x to 3.0

- Removed the `HubInterface::getCurrentHub()` and `HubInterface::setCurrentHub()` methods. Use `SentrySdk::getCurrentHub()` and `SentrySdk::setCurrentHub()` instead.
