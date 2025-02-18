PHP = php
PHPUNIT = vendor/bin/phpunit

# Coverage folder
COVERAGE_DIR = coverage

# Default
all: test

# Run PHPUnit
.PHONY: test
test:
	$(PHP) $(PHPUNIT) --testdox

# Generate coverage in HTML
.PHONY: coverage
coverage:
	$(PHP) $(PHPUNIT) --coverage-html $(COVERAGE_DIR)
	@echo "Coverage report generated in $(COVERAGE_DIR)/index.html"

# Clean report
.PHONY: clean
clean:
	rm -rf $(COVERAGE_DIR)
	@echo "Coverage reports cleaned."

# Full: clean, tests and generate coverage
.PHONY: full
test-coverage: clean test coverage
