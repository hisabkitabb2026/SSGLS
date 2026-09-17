# Build stage
FROM golang:1.21-alpine AS builder

WORKDIR /app

# Install build dependencies
RUN apk add --no-cache git ca-certificates

# Copy go mod files
COPY go.mod go.sum ./

# Download dependencies
RUN go mod download

# Copy source code
COPY cmd ./cmd
COPY internal ./internal

# Build the application
RUN CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -o settings-service ./cmd

# Final stage
FROM alpine:3.18

WORKDIR /root/

# Install runtime dependencies
RUN apk --no-cache add ca-certificates tzdata

# Copy the binary from builder
COPY --from=builder /app/settings-service .

# Expose port
EXPOSE 8005

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost:8005/health || exit 1

# Run the application
CMD ["./settings-service"]
