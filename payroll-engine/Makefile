BINARY := ../bin/payroll-engine
GO ?= go

.PHONY: build test fmt vet tidy clean

build:
	$(GO) build -trimpath -ldflags "-s -w -X main.version=$(shell git describe --tags --always --dirty 2>/dev/null || echo dev)" -o $(BINARY) ./cmd/payroll-engine

test:
	$(GO) test ./... -count=1

fmt:
	$(GO) fmt ./...

vet:
	$(GO) vet ./...

tidy:
	$(GO) mod tidy

clean:
	rm -f $(BINARY)
